<?php

namespace App\Services\WorkOrder;

use App\Models\WorkOrder;
use App\Models\WorkOrderFile;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class WorkOrderFileService
{
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Upload files for a work order
     */
    public function uploadFiles(WorkOrder $workOrder, array $files, ?int $fileTypeId, int $userId): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('work-orders/' . $workOrder->id, 'public');

                $workOrderFile = WorkOrderFile::create([
                    'work_order_id' => $workOrder->id,
                    'file_type_id' => $fileTypeId,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $userId,
                ]);

                $uploadedFiles[] = $workOrderFile;
            }
        }

        // Send notification if files were uploaded
        if (count($uploadedFiles) > 0) {
            $this->notificationService->fileUploaded($workOrder, $userId, count($uploadedFiles));
        }

        return $uploadedFiles;
    }

    /**
     * Delete a work order file
     */
    public function deleteFile(WorkOrderFile $file): bool
    {
        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        return $file->delete();
    }

    /**
     * Get all files for a work order
     */
    public function getFiles(WorkOrder $workOrder)
    {
        return $workOrder->files()->with(['fileType', 'uploadedBy'])->get();
    }
}
