<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WorkOrderFile;
use App\Models\WorkOrder;
use App\Models\FileType;
use Illuminate\Http\Request;
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
            'file_type_id' => 'required', // Can be ID or slug
            'file' => 'required|file|max:51200', // 50MB max
        ]);

        try {
            $user = $request->user();
            $workOrder = WorkOrder::findOrFail($workOrderId);
            
            // Resolve file_type_id - check if it's an ID or slug
            $fileTypeIdentifier = $validated['file_type_id'];
            
            // Try to find by ID first (if it's numeric)
            if (is_numeric($fileTypeIdentifier)) {
                $fileType = FileType::find($fileTypeIdentifier);
            } else {
                $fileType = null;
            }
            
            // If not found by ID, try to find by slug
            if (!$fileType) {
                $fileType = FileType::where('slug', $fileTypeIdentifier)->first();
            }
            
            // If still not found, throw error
            if (!$fileType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid file type. File type not found.',
                ], 422);
            }
            
            // Get uploaded file
            $uploadedFile = $request->file('file');

            // Generate unique filename: filetype_slug_randomstring.extension
            $extension = $uploadedFile->getClientOriginalExtension();
            $randomString = Str::random(8);
            $fileName = $fileType->slug . '_' . $randomString . '.' . $extension;

            // Store file in: work-orders/{work_order_number}/files/
            $filePath = $uploadedFile->storeAs(
                'work-orders/' . $workOrder->work_order_number . '/files',
                $fileName,
                'public'
            );

            // Get file size in KB
            $fileSizeKb = round($uploadedFile->getSize() / 1024, 2);

            // Create file record using the resolved file_type ID
            $file = WorkOrderFile::create([
                'work_order_id' => $workOrderId,
                'file_type_id' => $fileType->id, // Use the resolved ID
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $fileType->slug,
                'file_size_kb' => $fileSizeKb,
                'mime_type' => $uploadedFile->getMimeType(),
                'uploaded_by_id' => $user->id,
                'uploaded_at' => now(),
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
}
