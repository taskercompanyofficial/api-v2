<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrderFile;
use App\Models\WorkOrder;
use App\Models\FileType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipStream\ZipStream;
use ZipStream\CompressionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderFileController extends Controller
{
    /**
     * Get all files for a work order, grouped by file type
     */
    public function index(Request $request, $workOrderId)
    {
        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);

            $files = WorkOrderFile::where('work_order_id', $workOrderId)
                ->with('fileType')
                ->orderBy('file_type_id')
                ->orderBy('created_at', 'desc')
                ->get();

            // Group files by file type
            $groupedFiles = $files->groupBy('file_type_id');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'files' => $files,
                    'grouped' => $groupedFiles,
                ],
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a new file for a work order
     */
    public function store(Request $request, $workOrderId)
    {
        $validated = $request->validate([
            'file_type_id' => 'required|exists:file_types,id',
            'file' => 'required|file|max:20480', // 20MB
            'approval_status' => 'nullable|in:approved,rejected',
        ]);

        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $fileType = FileType::findOrFail($validated['file_type_id']);

            // Get uploaded file
            $uploadedFile = $request->file('file');

            // Generate unique filename: filetype_slug_randomstring.extension
            $extension = $uploadedFile->getClientOriginalExtension();
            $randomString = Str::random(8);
            $fileName = $fileType->slug . '_' . $randomString . '.' . $extension;

            // Store file in: work-orders/{work_order_number}
            $filePath = $uploadedFile->storeAs(
                'work-orders/' . $workOrder->work_order_number,
                $fileName,
                'public'
            );

            // Get file size in KB
            $fileSizeKb = round($uploadedFile->getSize() / 1024, 2);

            // Create file record
            $file = WorkOrderFile::create([
                'work_order_id' => $workOrderId,
                'file_type_id' => $validated['file_type_id'],
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $fileType->slug,
                'file_size_kb' => $fileSizeKb,
                'mime_type' => $uploadedFile->getMimeType(),
                'uploaded_by_id' => $user->id,
                'uploaded_at' => now(),
                'approval_status' => $validated['approval_status'] ?? 'pending',
            ]);

            // Load relationships
            $file->load('fileType', 'uploadedBy');

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded successfully',
                'data' => $file,
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Update file metadata
     */
    public function update(Request $request, $workOrderId, $fileId)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'file_name' => 'nullable|string|max:255',
        ]);

        try {
            $file = WorkOrderFile::where('work_order_id', $workOrderId)
                ->where('id', $fileId)
                ->firstOrFail();

            $file->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'File updated successfully',
                'data' => $file,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
    /**
     * Delete a file
     */
    public function destroy(Request $request, $workOrderId, $fileId)
    {
        try {
            $file = WorkOrderFile::where('work_order_id', $workOrderId)
                ->where('id', $fileId)
                ->firstOrFail();

            // Delete physical file from storage
            if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }

            // Delete database record
            $file->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'File deleted successfully',
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a single file
     */
    public function download(Request $request, $workOrderId, $fileId)
    {
        try {
            // Manual Sanctum token validation for direct/IDM downloads
            if (!$request->user() && $request->has('token')) {
                $tokenString = $request->query('token');
                if (str_contains($tokenString, '|')) {
                    $tokenString = explode('|', $tokenString)[1];
                }
                
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
                
                if ($accessToken && $accessToken->tokenable) {
                    $request->setUserResolver(fn() => $accessToken->tokenable);
                }
            }

            // Ensure we are authenticated (either via middleware OR via the manual token above)
            if (!$request->user()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
            }

            $file = WorkOrderFile::where('work_order_id', $workOrderId)
                ->where('id', $fileId)
                ->firstOrFail();

            // The file_path is relative to the 'public' disk root (storage/app/public)
            $filePath = storage_path('app/public/' . $file->file_path);

            if (!file_exists($filePath)) {
                // Fallback: check if it's stored in app/ directly
                $filePath = storage_path('app/' . $file->file_path);

                if (!file_exists($filePath)) {
                    Log::error("File not found for download: {$file->file_path} (ID: {$file->id})");
                    return response()->json([
                        'status' => 'error',
                        'message' => 'File not found on server',
                    ], 404);
                }
            }

            // Get actual filename from path (preserves original extension)
            $actualFileName = $file->file_name ?: basename($file->file_path);

            // Detect MIME type - prioritize stored MIME type, then detected, then default
            $mimeType = $file->mime_type;
            if (!$mimeType && file_exists($filePath)) {
                $mimeType = mime_content_type($filePath);
            }
            $mimeType = $mimeType ?: 'application/octet-stream';

            // Log for debugging
            Log::info("Downloading file: {$actualFileName} (ID: {$file->id}, MIME: {$mimeType})");

            return response()->download($filePath, $actualFileName, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $actualFileName . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        } catch (\Exception $err) {
            Log::error("Download error for file ID {$fileId}: " . $err->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while trying to download the file.',
            ], 500);
        }
    }

    /**
     * Update file approval status
     */
    public function updateApproval(Request $request, $workOrderId, $fileId)
    {
        $validated = $request->validate([
            'approval_status' => 'required|in:pending,approved,rejected',
            'approval_remark' => 'nullable|string|max:500',
        ]);

        try {
            $file = WorkOrderFile::where('work_order_id', $workOrderId)
                ->where('id', $fileId)
                ->firstOrFail();

            $file->update([
                'approval_status' => $validated['approval_status'],
                'approval_remark' => $validated['approval_remark'] ?? null,
            ]);

            $file->load('fileType', 'uploadedBy');

            return response()->json([
                'status' => 'success',
                'message' => 'File approval status updated successfully',
                'data' => $file,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Download files for a work order as a .tar.gz archive
     * Optionally accepts file_ids array to download only selected files
     */
    public function downloadAllAsArchive(Request $request, $workOrderId)
    {
        // Support manual Sanctum token validation for direct browser downloads
        if (!$request->user() && $request->has('token')) {
            $tokenString = $request->query('token');
            if (str_contains($tokenString, '|')) {
                $tokenString = explode('|', $tokenString)[1];
            }
            
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
            
            if ($accessToken && $accessToken->tokenable) {
                $request->setUserResolver(fn() => $accessToken->tokenable);
            }
        }

        // Must be authenticated
        if (!$request->user()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:work_order_files,id',
        ]);

        try {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            
            // Get file IDs from either request body (POST) or query string (GET)
            $fileIds = $request->input('file_ids');

            if (!$fileIds && $request->isMethod('get') && $request->has('file_ids')) {
                // If query string didn't parse as array automatically, handle it
                $fileIds = is_array($request->query('file_ids')) 
                    ? $request->query('file_ids') 
                    : explode(',', $request->query('file_ids'));
            }

            // Build query
            $query = WorkOrderFile::where('work_order_id', $workOrderId);
            if (!empty($fileIds)) {
                $query->whereIn('id', $fileIds);
            }

            $files = $query->with('fileType')->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No files found to download',
                ], 404);
            }

            $archiveName = 'work_order_' . $workOrder->work_order_number . '_files.zip';

            // PRE-CALCULATE size for progress bar support (IDM style)
            $totalArchiveSize = 0;
            $preparedFiles = [];

            foreach ($files as $file) {
                $filePath = storage_path('app/public/' . $file->file_path);
                if (!file_exists($filePath)) {
                    $filePath = storage_path('app/' . $file->file_path);
                }

                if (file_exists($filePath)) {
                    $fileSize = filesize($filePath);
                    $fileTypeName = $file->fileType?->name ?? $file->file_type ?? 'file';
                    
                    // Sanitize filename
                    $baseFileName = Str::slug($fileTypeName);
                    $extension = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
                    $zipTargetName = $baseFileName . '.' . $extension;

                    $preparedFiles[] = [
                        'path' => $filePath,
                        'name' => $zipTargetName,
                        'size' => $fileSize
                    ];

                    // ZIP Overhead for STORE mode with Data Descriptor is exactly:
                    // Local Header: 30 bytes + nameLen
                    // Data Descriptor: 16 bytes (ZipStream uses this by default for streaming)
                    // Central Directory Header: 46 bytes + nameLen
                    $totalArchiveSize += $fileSize + (30 + 16 + 46 + (2 * strlen($zipTargetName)));
                }
            }
            $totalArchiveSize += 22; // End of Central Directory record

            // For very large archives, Zip64 might be used adding 20+56 bytes, 
            // but for normal usage this is the exact byte count.

            // Ensure no output has started
            if (ob_get_level()) ob_end_clean();

            // Set headers manually for maximum speed and progress bar support
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $archiveName . '"');
            header('Content-Length: ' . $totalArchiveSize);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: public');
            header('X-Accel-Buffering: no');
            header('Connection: close');

            $zip = new ZipStream(
                outputName: $archiveName,
                sendHttpHeaders: false, // We sent them manually
                defaultCompressionMethod: CompressionMethod::STORE,
            );

            $usedFileNames = [];
            foreach ($preparedFiles as $p) {
                $fileName = $p['name'];
                // Ensure unique filenames within archive
                if (isset($usedFileNames[$fileName])) {
                    $usedFileNames[$fileName]++;
                    $parts = pathinfo($fileName);
                    $fileName = $parts['filename'] . '_' . $usedFileNames[$fileName] . '.' . ($parts['extension'] ?? 'file');
                } else {
                    $usedFileNames[$fileName] = 0;
                }

                $zip->addFileFromPath($fileName, $p['path']);
                // Flush occasionally to keep the connection alive
                if (function_exists('flush')) flush();
            }

            $zip->finish();
            exit; // Terminates the request immediately after finishing the ZIP stream

        } catch (\Exception $err) {
            Log::error("Archive streaming error for work order {$workOrderId}: " . $err->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while preparing the download.',
            ], 500);
        }
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMimeType(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return null;
        }

        $mimeToExtension = [
            // Videos
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-ms-wmv' => 'wmv',
            'video/webm' => 'webm',
            'video/3gpp' => '3gp',
            'video/x-flv' => 'flv',
            'video/x-matroska' => 'mkv',

            // Images
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'image/heic' => 'heic',
            'image/heif' => 'heif',

            // Audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/flac' => 'flac',
            'audio/x-m4a' => 'm4a',
            'audio/mp4' => 'm4a',

            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/html' => 'html',

            // Archives
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz',
            'application/x-tar' => 'tar',
        ];

        return $mimeToExtension[$mimeType] ?? null;
    }
}
