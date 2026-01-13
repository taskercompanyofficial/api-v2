<?php

namespace App\Services\WorkOrder;

use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderHistory;
use Exception;
use Illuminate\Support\Facades\Log;

class WorkOrderStatusService
{
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Accept work order
     */
    public function acceptWorkOrder(WorkOrder $workOrder, int $userId): array
    {
        if ($workOrder->accepted_at) {
            throw new Exception('Work order already accepted');
        }

        // Find "Dispatched" status and "Technician Accepted" sub-status
        $dispatchedStatus = WorkOrderStatus::where('slug', 'dispatched')
            ->whereNull('parent_id')
            ->first();

        if ($dispatchedStatus) {
            $technicianAcceptedStatus = WorkOrderStatus::where('slug', 'technician-accepted')
                ->where('parent_id', $dispatchedStatus->id)
                ->first();

            if ($technicianAcceptedStatus) {
                $workOrder->status_id = $dispatchedStatus->id;
                $workOrder->sub_status_id = $technicianAcceptedStatus->id;
            }
        }

        $workOrder->accepted_at = now();
        $workOrder->updated_by = $userId;
        $workOrder->save();

        // Log acceptance
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'accepted',
            description: "Work order accepted",
            metadata: [
                'accepted_by' => $userId,
                'accepted_at' => $workOrder->accepted_at,
            ]
        );

        // Send notification
        $this->notificationService->workOrderAccepted(
            $workOrder,
            $userId
        );

        return [
            'status' => 'success',
            'message' => 'Work order accepted successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Start service
     */
    public function startService(WorkOrder $workOrder, int $userId): array
    {
        $status = WorkOrderStatus::where('slug', 'in-progress')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "In Progress" not found');
        }

        $subStatus = WorkOrderStatus::where('slug', 'work-started')
            ->where('parent_id', $status->id)
            ->first();

        if (!$subStatus) {
            throw new Exception('Sub-status "Work Started" not found');
        }

        $workOrder->status_id = $status->id;
        $workOrder->sub_status_id = $subStatus->id;
        $workOrder->service_start_date = now()->toDateTimeString();
        $workOrder->service_start_time = now()->toTimeString();
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'status_updated',
            description: "Service started - Status updated to {$status->name} / {$subStatus->name}",
            metadata: [
                'old_status_id' => null,
                'new_status_id' => $status->id,
                'old_sub_status_id' => null,
                'new_sub_status_id' => $subStatus->id,
                'service_start_date' => $workOrder->service_start_date,
                'service_start_time' => $workOrder->service_start_time,
            ]
        );

        // Send notification
        $this->notificationService->serviceStarted($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Service started successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Start work
     */
    public function startWork(WorkOrder $workOrder, int $userId): array
    {
        // Find "In Progress" status and "Work Started" sub-status
        $status = WorkOrderStatus::where('slug', 'in-progress')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "In Progress" not found');
        }

        $subStatus = WorkOrderStatus::where('slug', 'work-started')
            ->where('parent_id', $status->id)
            ->first();

        if (!$subStatus) {
            throw new Exception('Sub-status "Work Started" not found');
        }

        $oldSubStatusId = $workOrder->sub_status_id;

        // Update work order
        $workOrder->status_id = $status->id;
        $workOrder->sub_status_id = $subStatus->id;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        // Log in history
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'status_updated',
            description: "Work started - Sub-status updated to {$subStatus->name}",
            metadata: [
                'old_sub_status_id' => $oldSubStatusId,
                'new_sub_status_id' => $subStatus->id,
            ]
        );

        // Send notification
        $this->notificationService->workStarted($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Work started successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Complete service
     */
    public function completeService(WorkOrder $workOrder, int $userId): array
    {
        // Check for required files
        $requiredFiles = \App\Models\ServiceRequiredFile::where('parent_service_id', $workOrder->parent_service_id)
            ->where('is_required', true)
            ->with('fileType')
            ->get();

        if ($requiredFiles->isNotEmpty()) {
            $uploadedFileTypeIds = $workOrder->files()->pluck('file_type_id')->unique()->toArray();
            $missingFiles = $requiredFiles->filter(function ($req) use ($uploadedFileTypeIds) {
                return !in_array($req->file_type_id, $uploadedFileTypeIds);
            });

            if ($missingFiles->isNotEmpty()) {
                $missingNames = $missingFiles->map(function ($req) {
                    return $req->fileType->name ?? 'Unknown Type';
                })->values()->toArray();

                throw new \App\Exceptions\MissingFilesException("Required files missing. Please upload them before completing.", $missingNames);
            }
        }

        $status = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "Completed" not found');
        }
        if ($workOrder->warranty_type_id == 1) {
            $missingFields = [];
            if (!$workOrder->indoor_serial_number) {
                $missingFields[] = 'Indoor Serial Number';
            }
            if (!$workOrder->outdoor_serial_number) {
                $missingFields[] = 'Outdoor Serial Number';
            }
            if (!$workOrder->product_indoor_model) {
                $missingFields[] = 'Product Indoor Model';
            }
            if (!$workOrder->product_outdoor_model) {
                $missingFields[] = 'Product Outdoor Model';
            }
            if (!$workOrder->purchase_date) {
                $missingFields[] = 'Purchase Date';
            }

            if (!empty($missingFields)) {
                $fieldsList = implode(', ', $missingFields);
                throw new Exception("Warranty information is incomplete. Missing: {$fieldsList}. Please update the work order before completing.");
            }
        }
        // Get sub-status that belongs to the completed status
        $subStatus = WorkOrderStatus::where('slug', 'pending-service-centre-complete')
            ->where('parent_id', $status->id)
            ->first();

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        $workOrder->status_id = $status->id;
        $workOrder->sub_status_id = $subStatus?->id;
        $workOrder->service_end_date = now()->toDateTimeString();
        $workOrder->service_end_time = now()->toTimeString();
        $workOrder->completed_at = now();
        $workOrder->completed_by = $userId;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'completed',
            description: "Service completed - Status updated to {$status->name}",
            metadata: [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $status->id,
                'old_sub_status_id' => $oldSubStatusId,
                'new_sub_status_id' => $subStatus?->id,
                'service_end_date' => $workOrder->service_end_date,
                'service_end_time' => $workOrder->service_end_time,
                'completed_by' => $userId,
            ]
        );

        // Send notification
        $this->notificationService->workOrderCompleted($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Service completed successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Mark as part in demand
     */
    public function markAsPartInDemand(WorkOrder $workOrder, int $userId): array
    {
        $workOrder->load('parts');

        if ($workOrder->parts->isEmpty()) {
            throw new Exception('Cannot mark as part in demand. No parts have been demanded for this work order.');
        }

        $status = WorkOrderStatus::where('slug', 'part-in-demand')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "Part in Demand" not found');
        }

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        $workOrder->status_id = $status->id;
        $workOrder->sub_status_id = null;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'status_updated',
            description: "Work order marked as Part in Demand",
            metadata: [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $status->id,
                'old_sub_status_id' => $oldSubStatusId,
                'new_sub_status_id' => null,
                'parts_count' => $workOrder->parts->count(),
            ]
        );

        // Send notification
        $this->notificationService->partInDemand($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Work order marked as Part in Demand successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Complete from part demand
     */
    public function completeFromPartDemand(WorkOrder $workOrder, int $userId): array
    {
        $workOrder->load('parts');

        if ($workOrder->status->slug !== 'part-in-demand') {
            throw new Exception('Work order is not in Part in Demand status');
        }

        // Check if all parts are installed
        $pendingParts = $workOrder->parts->filter(function ($part) {
            return in_array($part->status, ['requested', 'dispatched', 'received']);
        });

        if ($pendingParts->isNotEmpty()) {
            $pendingStatuses = $pendingParts->pluck('status')->unique()->implode(', ');
            throw new Exception("Cannot complete. Some parts are still pending (Status: {$pendingStatuses}). All parts must be installed before completing.");
        }

        $status = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "Completed" not found');
        }

        $oldStatusId = $workOrder->status_id;

        $workOrder->status_id = $status->id;
        $workOrder->sub_status_id = null;
        $workOrder->service_end_date = now()->toDateString();
        $workOrder->service_end_time = now()->toTimeString();
        $workOrder->completed_at = now();
        $workOrder->completed_by = $userId;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'completed',
            description: "Work order completed from Part in Demand status",
            metadata: [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $status->id,
                'service_end_date' => $workOrder->service_end_date,
                'service_end_time' => $workOrder->service_end_time,
                'completed_by' => $userId,
                'parts_installed' => $workOrder->parts->where('status', 'installed')->count(),
            ]
        );

        return [
            'status' => 'success',
            'message' => 'Work order completed successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Schedule work order appointment
     */
    public function scheduleWorkOrder(WorkOrder $workOrder, array $data, int $userId): array
    {
        // Prevent scheduling if completed or cancelled
        if ($workOrder->completed_at || $workOrder->cancelled_at) {
            throw new Exception('Cannot schedule completed or cancelled work order');
        }

        // Update appointment date and time
        $workOrder->update([
            'appointment_date' => $data['scheduled_date'],
            'appointment_time' => $data['scheduled_time'],
            'updated_by' => $userId,
        ]);

        // Update status to Dispatched - Technician Accepted if work order has an assigned technician
        if ($workOrder->assigned_to_id) {
            $dispatchedStatus = WorkOrderStatus::where('slug', 'dispatched')->first();
            $technicianAcceptedSubStatus = WorkOrderStatus::where('slug', 'technician-accepted')
                ->where('parent_id', $dispatchedStatus?->id)
                ->first();

            if ($dispatchedStatus && $technicianAcceptedSubStatus) {
                $workOrder->status_id = $dispatchedStatus->id;
                $workOrder->sub_status_id = $technicianAcceptedSubStatus->id;
                $workOrder->save();
            }
        }

        // Add remarks if provided
        if (isset($data['remarks']) && $data['remarks']) {
            $currentRemarks = $workOrder->technician_remarks ?? '';
            $newRemarks = $currentRemarks
                ? $currentRemarks . "\n\n[Scheduled " . now()->format('Y-m-d H:i') . "]: " . $data['remarks']
                : "[Scheduled " . now()->format('Y-m-d H:i') . "]: " . $data['remarks'];

            $workOrder->update(['technician_remarks' => $newRemarks]);
        }

        // Log scheduling history
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'scheduled',
            description: "Work order scheduled for {$workOrder->appointment_date} at {$workOrder->appointment_time}",
            metadata: [
                'appointment_date' => $workOrder->appointment_date,
                'appointment_time' => $workOrder->appointment_time,
                'remarks' => $data['remarks'] ?? null,
            ]
        );

        // Send notification
        $scheduledDate = $workOrder->appointment_date . ' at ' . $workOrder->appointment_time;
        $this->notificationService->workOrderScheduled($workOrder, $userId, $scheduledDate);

        return [
            'status' => 'success',
            'message' => 'Work order scheduled successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Cancel work order
     */
    public function cancelWorkOrder(WorkOrder $workOrder, string $reason, int $userId): array
    {
        // Prevent cancellation if already completed or cancelled
        if ($workOrder->completed_at) {
            throw new Exception('Cannot cancel a completed work order');
        }

        if ($workOrder->cancelled_at) {
            throw new Exception('Work order is already cancelled');
        }

        // Get the Cancelled status
        $cancelledStatus = WorkOrderStatus::where('slug', 'cancelled')->first();
        $customerCancelledSubStatus = WorkOrderStatus::where('slug', 'customer-cancelled')
            ->where('parent_id', $cancelledStatus?->id)
            ->first();

        // Update work order
        $workOrder->update([
            'status_id' => $cancelledStatus?->id,
            'sub_status_id' => $customerCancelledSubStatus?->id,
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'reject_reason' => $reason,
            'updated_by' => $userId,
        ]);

        // Log history
        $staff = \App\Models\Staff::find($userId);
        $staffName = $staff ? "{$staff->first_name} {$staff->last_name}" : 'System';

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'cancelled',
            description: "Work order cancelled by {$staffName}. Reason: {$reason}",
            metadata: [
                'cancellation_reason' => $reason,
                'cancelled_by_staff_id' => $userId,
                'cancelled_by_staff_name' => $staffName,
                'status_changed_to' => $cancelledStatus?->name,
                'sub_status_changed_to' => $customerCancelledSubStatus?->name,
            ]
        );

        // Send notification
        $this->notificationService->workOrderCancelled($workOrder, $userId, $reason);

        return [
            'status' => 'success',
            'message' => 'Work order cancelled successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Approve work order (after completion)
     * Checks if all files are approved before moving to closed status
     */
    public function approveWorkOrder(WorkOrder $workOrder, int $userId): array
    {
        $completeStatus = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        // Check if work order is in completed status
        if (!$completeStatus || $workOrder->status_id !== $completeStatus->id) {
            throw new Exception('Work order must be in Completed status to approve');
        }

        // Query files directly (relationship was not loading properly)
        $files = \App\Models\WorkOrderFile::where('work_order_id', $workOrder->id)->get();

        // Check if any file has pending or rejected status
        $pendingOrRejectedFiles = $files->filter(function ($file) {
            return in_array($file->approval_status, ['pending', 'rejected']);
        });

        $completedStatus = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        $feedbackPendingSubStatus = WorkOrderStatus::where('slug', 'feedback-pending')
            ->where('parent_id', $completedStatus?->id)
            ->first();

        if (!$feedbackPendingSubStatus) {
            throw new Exception('Sub-status "Feedback Pending" not found');
        }
        if ($pendingOrRejectedFiles->isNotEmpty()) {
            // Get pending-feedback sub-status under completed

            $oldSubStatusId = $workOrder->sub_status_id;
            $workOrder->sub_status_id = $feedbackPendingSubStatus->id;
            $workOrder->updated_by = $userId;
            $workOrder->save();

            // Get file names that need attention (use file type name or file name)
            $fileNames = $pendingOrRejectedFiles->map(function ($file) {
                return $file->fileType?->name ?? $file->file_name ?? 'Unknown';
            })->values()->toArray();

            WorkOrderHistory::log(
                workOrderId: $workOrder->id,
                actionType: 'status_updated',
                description: "Work order set to Feedback Pending - Some files need approval",
                metadata: [
                    'old_sub_status_id' => $oldSubStatusId,
                    'new_sub_status_id' => $feedbackPendingSubStatus->id,
                    'pending_files' => $fileNames,
                ]
            );

            throw new \App\Exceptions\MissingFilesException(
                "Some files are pending approval or rejected. Please review them.",
                $fileNames
            );
        }

        $oldSubStatusId = $workOrder->sub_status_id;

        $workOrder->sub_status_id = $feedbackPendingSubStatus->id;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'feedback_pending',
            description: "Work order set to Feedback Pending",
            metadata: [
                'old_sub_status_id' => $oldSubStatusId,
            ]
        );

        // Send notification
        $this->notificationService->workOrderApproved($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Work order set to Feedback Pending',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Close work order (from Feedback Pending status)
     * All files must be approved
     */
    public function closeWorkOrder(WorkOrder $workOrder, int $userId): array
    {
        $warrenty_type = $workOrder->warranty_type_id;
        // Check if work order is in completed status with feedback-pending sub-status
        $completedStatus = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        $feedbackPendingSubStatus = WorkOrderStatus::where('slug', 'feedback-pending')
            ->where('parent_id', $completedStatus?->id)
            ->first();

        if (!$completedStatus || $workOrder->status_id !== $completedStatus->id) {
            throw new Exception('Work order must be in Completed status to close');
        }

        if ($feedbackPendingSubStatus && $workOrder->sub_status_id !== $feedbackPendingSubStatus->id) {
            throw new Exception('Work order must be in Feedback Pending status to close');
        }

        // Check if customer feedback exists
        if (!$workOrder->feedback()->exists()) {
            throw new Exception('Customer feedback is required before closing the work order');
        }
        if ($warrenty_type == 1) {
            if (!$workOrder->brand_complain_number) {
                throw new Exception('Brand Complain number required before closing the work order.');
            }
        }

        // Verify all files are approved
        $files = \App\Models\WorkOrderFile::where('work_order_id', $workOrder->id)->get();
        $pendingOrRejectedFiles = $files->filter(function ($file) {
            return in_array($file->approval_status, ['pending', 'rejected']);
        });

        if ($pendingOrRejectedFiles->isNotEmpty()) {
            $fileNames = $pendingOrRejectedFiles->map(function ($file) {
                return $file->fileType?->name ?? $file->file_name ?? 'Unknown';
            })->values()->toArray();

            throw new \App\Exceptions\MissingFilesException(
                "Some files are still pending approval or rejected.",
                $fileNames
            );
        }

        // Move to Closed status
        $closedStatus = WorkOrderStatus::where('slug', 'closed')
            ->whereNull('parent_id')
            ->first();
        $closedSubStatus = WorkOrderStatus::where('slug', 'closed-completed')->where('parent_id', $closedStatus?->id)
            ->first();

        if (!$closedStatus) {
            throw new Exception('Status "Closed" not found');
        }
        if (!$closedSubStatus) {
            throw new Exception('Sub-status "Closed Completed" not found');
        }

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        $workOrder->status_id = $closedStatus->id;
        $workOrder->sub_status_id = $closedSubStatus->id;
        $workOrder->closed_at = now();
        $workOrder->closed_by = $userId;
        $workOrder->is_locked = true;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'closed',
            description: "Work order closed",
            metadata: [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $closedStatus->id,
                'old_sub_status_id' => $oldSubStatusId,
                'closed_by' => $userId,
            ]
        );

        // Send notification
        $this->notificationService->workOrderClosed($workOrder, $userId);

        return [
            'status' => 'success',
            'message' => 'Work order closed successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }

    /**
     * Reject completion and send back to rework
     * Moves work order from Completed back to In Progress / Rework Required
     */
    public function rejectCompletion(WorkOrder $workOrder, string $reason, int $userId): array
    {
        $completeStatus = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        // Check if work order is in completed status
        if (!$completeStatus || $workOrder->status_id !== $completeStatus->id) {
            throw new Exception('Work order must be in Completed status to reject');
        }

        // Get In Progress status
        $inProgressStatus = WorkOrderStatus::where('slug', 'in-progress')
            ->whereNull('parent_id')
            ->first();

        if (!$inProgressStatus) {
            throw new Exception('Status "In Progress" not found');
        }

        // Get Rework Required sub-status (or Work Started if rework doesn't exist)
        $reworkSubStatus = WorkOrderStatus::where('slug', 'rework-required')
            ->where('parent_id', $inProgressStatus->id)
            ->first();

        if (!$reworkSubStatus) {
            // Fallback to work-started if rework-required doesn't exist
            $reworkSubStatus = WorkOrderStatus::where('slug', 'work-started')
                ->where('parent_id', $inProgressStatus->id)
                ->first();
        }

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        // Update work order
        $workOrder->status_id = $inProgressStatus->id;
        $workOrder->sub_status_id = $reworkSubStatus?->id;
        $workOrder->completed_at = null;
        $workOrder->completed_by = null;
        $workOrder->service_end_date = null;
        $workOrder->service_end_time = null;
        $workOrder->updated_by = $userId;
        $workOrder->save();

        // Add rejection reason to technician remarks
        $currentRemarks = $workOrder->technician_remarks ?? '';
        $newRemarks = $currentRemarks
            ? $currentRemarks . "\n\n[Rework Required " . now()->format('Y-m-d H:i') . "]: " . $reason
            : "[Rework Required " . now()->format('Y-m-d H:i') . "]: " . $reason;

        $workOrder->update(['technician_remarks' => $newRemarks]);

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'rework_required',
            description: "Completion rejected - Sent back to rework. Reason: {$reason}",
            metadata: [
                'old_status_id' => $oldStatusId,
                'new_status_id' => $inProgressStatus->id,
                'old_sub_status_id' => $oldSubStatusId,
                'new_sub_status_id' => $reworkSubStatus?->id,
                'rejection_reason' => $reason,
                'rejected_by' => $userId,
            ]
        );

        // Send notification to assigned staff
        $this->notificationService->workOrderSentBackForRework($workOrder, $userId, $reason);

        return [
            'status' => 'success',
            'message' => 'Work order sent back for rework',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }
}
