<?php

namespace App\Services\WorkOrder;

use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderHistory;
use Exception;

class WorkOrderStatusService
{
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

        $subStatus = WorkOrderStatus::where('slug', 'going-to-work')
            ->where('parent_id', $status->id)
            ->first();

        if (!$subStatus) {
            throw new Exception('Sub-status "Going to work" not found');
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
                })->implode(', ');

                throw new Exception("Required files missing: {$missingNames}. Please upload them before completing.");
            }
        }

        $status = WorkOrderStatus::where('slug', 'completed')
            ->whereNull('parent_id')
            ->first();

        if (!$status) {
            throw new Exception('Status "Completed" not found');
        }

        $subStatus = WorkOrderStatus::where('parent_id', $status->id)->first();

        $oldStatusId = $workOrder->status_id;
        $oldSubStatusId = $workOrder->sub_status_id;

        $workOrder->status_id = $status->id;
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

        return [
            'status' => 'success',
            'message' => 'Work order cancelled successfully',
            'data' => $workOrder->fresh(['status', 'subStatus']),
        ];
    }
}
