<?php

namespace App\Services\WorkOrder;

use App\Models\Notification;
use App\Models\Staff;
use App\Models\WorkOrder;

class WorkOrderNotificationService
{
    /**
     * Send notification to a specific user
     */
    public function notifyUser(int $userId, string $title, string $message, string $type, WorkOrder $workOrder): void
    {
        $workOrderCode = $workOrder->code ?? "WO-{$workOrder->id}";

        Notification::createNotification(
            $userId,
            'App\Models\Staff',
            $title,
            $message,
            $type,
            [
                'link' => "/crm/work-orders/{$workOrder->id}",
                'work_order_id' => $workOrder->id,
                'work_order_code' => $workOrderCode,
            ]
        );
    }

    /**
     * Send notification to all active staff members
     */
    public function notifyAllStaff(string $title, string $message, string $type, WorkOrder $workOrder, ?int $excludeUserId = null): void
    {
        $query = Staff::where('status_id', 1);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        $staffMembers = $query->get();

        foreach ($staffMembers as $staff) {
            $this->notifyUser($staff->id, $title, $message, $type, $workOrder);
        }
    }

    /**
     * Send notification to assigned staff only
     */
    public function notifyAssignedStaff(WorkOrder $workOrder, string $title, string $message, string $type): void
    {
        if ($workOrder->assigned_to) {
            $this->notifyUser($workOrder->assigned_to, $title, $message, $type, $workOrder);
        }
    }

    /**
     * Get staff full name
     */
    protected function getStaffName(?int $staffId): string
    {
        if (!$staffId) {
            return 'System';
        }

        $staff = Staff::find($staffId);
        return $staff ? "{$staff->first_name} {$staff->last_name}" : 'Unknown';
    }

    /**
     * Get work order display code
     */
    protected function getWorkOrderCode(WorkOrder $workOrder): string
    {
        return $workOrder->code ?? "WO-{$workOrder->id}";
    }

    // ==========================================
    // Work Order Status Notifications
    // ==========================================

    public function workOrderCreated(WorkOrder $workOrder, int $createdBy): void
    {
        $staffName = $this->getStaffName($createdBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "New Work Order Created",
            "{$staffName} created work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $createdBy
        );
    }

    public function workOrderUpdated(WorkOrder $workOrder, int $updatedBy): void
    {
        $staffName = $this->getStaffName($updatedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Updated",
            "{$staffName} updated work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $updatedBy
        );
    }

    public function workOrderAccepted(WorkOrder $workOrder, int $acceptedBy): void
    {
        $staffName = $this->getStaffName($acceptedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Accepted",
            "{$staffName} accepted work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $acceptedBy
        );
    }

    public function workOrderRejected(WorkOrder $workOrder, int $rejectedBy, string $reason): void
    {
        $staffName = $this->getStaffName($rejectedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Rejected",
            "{$staffName} rejected work order #{$code}: {$reason}. Click to view.",
            'work-order',
            $workOrder,
            $rejectedBy
        );
    }

    public function serviceStarted(WorkOrder $workOrder, int $startedBy): void
    {
        $staffName = $this->getStaffName($startedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Service Started",
            "{$staffName} started service for #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $startedBy
        );
    }

    public function workStarted(WorkOrder $workOrder, int $startedBy): void
    {
        $staffName = $this->getStaffName($startedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Started",
            "{$staffName} started working on #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $startedBy
        );
    }

    public function workOrderCompleted(WorkOrder $workOrder, int $completedBy): void
    {
        $staffName = $this->getStaffName($completedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Completed",
            "{$staffName} completed work order #{$code}. Pending approval. Click to view.",
            'work-order',
            $workOrder,
            $completedBy
        );
    }

    public function workOrderApproved(WorkOrder $workOrder, int $approvedBy): void
    {
        $staffName = $this->getStaffName($approvedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Approved",
            "{$staffName} approved work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $approvedBy
        );
    }

    public function workOrderClosed(WorkOrder $workOrder, int $closedBy): void
    {
        $staffName = $this->getStaffName($closedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Closed",
            "{$staffName} closed work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $closedBy
        );
    }

    public function workOrderCancelled(WorkOrder $workOrder, int $cancelledBy, string $reason): void
    {
        $staffName = $this->getStaffName($cancelledBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Cancelled",
            "{$staffName} cancelled #{$code}: {$reason}. Click to view.",
            'work-order',
            $workOrder,
            $cancelledBy
        );
    }

    public function workOrderSentBackForRework(WorkOrder $workOrder, int $rejectedBy, string $reason): void
    {
        $staffName = $this->getStaffName($rejectedBy);
        $code = $this->getWorkOrderCode($workOrder);

        // Notify assigned staff
        $this->notifyAssignedStaff(
            $workOrder,
            "Rework Required",
            "{$staffName} sent #{$code} back for rework: {$reason}. Click to view.",
            'work-order'
        );
    }

    public function partInDemand(WorkOrder $workOrder, int $markedBy): void
    {
        $staffName = $this->getStaffName($markedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Part Required",
            "{$staffName} marked #{$code} as waiting for parts. Click to view.",
            'part-request',
            $workOrder,
            $markedBy
        );
    }

    public function workOrderScheduled(WorkOrder $workOrder, int $scheduledBy, string $scheduledDate): void
    {
        $staffName = $this->getStaffName($scheduledBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAssignedStaff(
            $workOrder,
            "Appointment Scheduled",
            "{$staffName} scheduled #{$code} for {$scheduledDate}. Click to view.",
            'reminder'
        );
    }

    // ==========================================
    // Assignment Notifications
    // ==========================================

    public function staffAssigned(WorkOrder $workOrder, int $assignedStaffId, int $assignedBy): void
    {
        $assignerName = $this->getStaffName($assignedBy);
        $assigneeName = $this->getStaffName($assignedStaffId);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Staff Assigned",
            "{$assignerName} assigned #{$code} to {$assigneeName}. Click to view.",
            'assignment',
            $workOrder,
            $assignedBy
        );
    }

    public function staffReassigned(WorkOrder $workOrder, int $oldStaffId, int $newStaffId, int $reassignedBy): void
    {
        $reassignerName = $this->getStaffName($reassignedBy);
        $oldStaffName = $this->getStaffName($oldStaffId);
        $newStaffName = $this->getStaffName($newStaffId);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Staff Reassigned",
            "{$reassignerName} reassigned #{$code} from {$oldStaffName} to {$newStaffName}. Click to view.",
            'assignment',
            $workOrder,
            $reassignedBy
        );
    }

    // ==========================================
    // File & Document Notifications
    // ==========================================

    public function fileUploaded(WorkOrder $workOrder, int $uploadedBy, int $fileCount): void
    {
        $staffName = $this->getStaffName($uploadedBy);
        $code = $this->getWorkOrderCode($workOrder);
        $fileText = $fileCount === 1 ? 'file' : 'files';

        $this->notifyAllStaff(
            "Files Uploaded",
            "{$staffName} uploaded {$fileCount} {$fileText} to #{$code}. Click to view.",
            'document',
            $workOrder,
            $uploadedBy
        );
    }

    // ==========================================
    // Feedback Notifications
    // ==========================================

    public function feedbackAdded(WorkOrder $workOrder, int $addedBy, int $rating): void
    {
        $staffName = $this->getStaffName($addedBy);
        $code = $this->getWorkOrderCode($workOrder);
        $stars = str_repeat('⭐', $rating);

        $this->notifyAllStaff(
            "Customer Feedback",
            "{$staffName} added feedback for #{$code} ({$stars}). Click to view.",
            'document',
            $workOrder,
            $addedBy
        );
    }

    // ==========================================
    // Reminder Notifications
    // ==========================================

    public function reminderSent(WorkOrder $workOrder, int $sentBy, string $remark): void
    {
        $staffName = $this->getStaffName($sentBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAssignedStaff(
            $workOrder,
            "Reminder",
            "{$staffName} sent a reminder for #{$code}: {$remark}. Click to view.",
            'reminder'
        );
    }

    // ==========================================
    // Duplicate & Reopen Notifications
    // ==========================================

    public function workOrderDuplicated(WorkOrder $originalWorkOrder, WorkOrder $newWorkOrder, int $duplicatedBy): void
    {
        $staffName = $this->getStaffName($duplicatedBy);
        $originalCode = $this->getWorkOrderCode($originalWorkOrder);
        $newCode = $this->getWorkOrderCode($newWorkOrder);

        $this->notifyAllStaff(
            "Work Order Duplicated",
            "{$staffName} duplicated #{$originalCode} as #{$newCode}. Click to view.",
            'work-order',
            $newWorkOrder,
            $duplicatedBy
        );
    }

    public function workOrderReopened(WorkOrder $originalWorkOrder, WorkOrder $newWorkOrder, int $reopenedBy): void
    {
        $staffName = $this->getStaffName($reopenedBy);
        $originalCode = $this->getWorkOrderCode($originalWorkOrder);
        $newCode = $this->getWorkOrderCode($newWorkOrder);

        $this->notifyAllStaff(
            "Work Order Reopened",
            "{$staffName} reopened #{$originalCode} as #{$newCode}. Click to view.",
            'work-order',
            $newWorkOrder,
            $reopenedBy
        );
    }
}
