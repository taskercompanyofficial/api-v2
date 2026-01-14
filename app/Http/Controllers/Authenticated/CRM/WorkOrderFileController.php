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
            'file' => 'required|file',
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
            $actualFileName = basename($file->file_path);

            // Detect MIME type from actual file if not stored or if stored one seems wrong
            $detectedMimeType = mime_content_type($filePath);
            $mimeType = $detectedMimeType ?: ($file->mime_type ?: 'application/octet-stream');

            // Log for debugging
            Log::info("Downloading file: {$actualFileName} (Stored MIME: {$file->mime_type}, Detected MIME: {$detectedMimeType})");

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
        $validated = $request->validate([
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'required|exists:work_order_files,id',
        ]);

        try {
            // Remove limits for large file handling
            set_time_limit(0); // No time limit
            ini_set('memory_limit', '-1'); // No memory limit

            $workOrder = WorkOrder::findOrFail($workOrderId);

            // Get file IDs from request (optional - if not provided, get all files)
            $fileIds = $request->input('file_ids');

            // Build query
            $query = WorkOrderFile::where('work_order_id', $workOrderId);

            // If specific file IDs are provided, filter by them
            if (!empty($fileIds) && is_array($fileIds)) {
                $query->whereIn('id', $fileIds);
            }


            $files = $query->with('fileType')->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No files found for this work order',
                ], 404);
            }

            // Create temporary directory for archive
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Archive filenames
            $archiveName = 'work_order_' . $workOrder->work_order_number . '_files';
            $tarPath = $tempDir . '/' . $archiveName . '.tar';
            $gzPath = $tarPath . '.gz';

            // Remove existing archives if they exist
            if (file_exists($gzPath)) {
                unlink($gzPath);
            }
            if (file_exists($tarPath)) {
                unlink($tarPath);
            }

            // Create TAR archive
            $phar = new \PharData($tarPath);

            $addedFiles = 0;
            $usedFileNames = [];
            foreach ($files as $file) {
                // Get the full path to the file
                $filePath = storage_path('app/public/' . $file->file_path);

                // Fallback: check if it's stored in app/ directly
                if (!file_exists($filePath)) {
                    $filePath = storage_path('app/' . $file->file_path);
                }

                if (file_exists($filePath)) {
                    // Detect actual MIME type from the file content
                    $detectedMimeType = mime_content_type($filePath);

                    // Get extension from MIME type (more accurate)
                    $extension = $this->getExtensionFromMimeType($detectedMimeType)
                        ?: pathinfo($file->file_path, PATHINFO_EXTENSION);

                    // Use file type name as the filename
                    $fileTypeName = $file->fileType?->name ?? $file->file_type ?? 'file';

                    // Create filename with correct extension
                    $fileName = $fileTypeName . '.' . $extension;

                    // Ensure unique filenames by appending counter if duplicate
                    if (in_array($fileName, $usedFileNames)) {
                        $counter = 1;
                        do {
                            $fileName = $fileTypeName . '_' . $counter . '.' . $extension;
                            $counter++;
                        } while (in_array($fileName, $usedFileNames));
                    }
                    $usedFileNames[] = $fileName;

                    $phar->addFile($filePath, $fileName);
                    $addedFiles++;
                } else {
                    Log::warning("File not found for archive: {$file->file_path} (ID: {$file->id})");
                }
            }

            if ($addedFiles === 0) {
                // Clean up
                if (file_exists($tarPath)) {
                    unlink($tarPath);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'No files could be found on the server',
                ], 404);
            }

            // Compress to .tar.gz
            $phar->compress(\Phar::GZ);

            // Remove the uncompressed .tar file
            if (file_exists($tarPath)) {
                unlink($tarPath);
            }

            // Return the .tar.gz file for download
            $downloadName = $archiveName . '.tar.gz';

            return response()->download($gzPath, $downloadName, [
                'Content-Type' => 'application/gzip',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $err) {
            Log::error("Archive creation error for work order {$workOrderId}: " . $err->getMessage());

            // Clean up any partial files
            $tempDir = storage_path('app/temp');
            $archiveName = 'work_order_' . ($workOrder->work_order_number ?? $workOrderId) . '_files';
            $tarPath = $tempDir . '/' . $archiveName . '.tar';
            $gzPath = $tarPath . '.gz';

            if (file_exists($gzPath)) {
                unlink($gzPath);
            }
            if (file_exists($tarPath)) {
                unlink($tarPath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating the archive: ' . $err->getMessage(),
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
