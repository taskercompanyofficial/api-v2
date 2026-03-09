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
     * Send notification to all active staff members with CRM access in the same branch
     */
    public function notifyAllStaff(string $title, string $message, string $type, WorkOrder $workOrder, ?int $excludeUserId = null): void
    {
        // Suppress notification if the actor is a CRM user
        if ($excludeUserId && $this->isCrmUser($excludeUserId)) {
            return;
        }

        $query = Staff::where('status_id', 1)
            ->where('has_access_in_crm', 1)
            ->where('branch_id', $workOrder->branch_id);

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
     * Get actor name (Staff or Vendor)
     */
    public function getActorName(?int $id, bool $isVendor = false): string
    {
        if (!$id) {
            return 'System';
        }

        if ($isVendor) {
            $vendor = \App\Models\Vendor::find($id);
            return $vendor ? $vendor->name : 'Unknown Vendor';
        }

        $staff = Staff::find($id);
        return $staff ? "{$staff->first_name} {$staff->last_name}" : 'Unknown';
    }

    /**
     * Get work order display code
     */
    protected function getWorkOrderCode(WorkOrder $workOrder): string
    {
        return $workOrder->work_order_number ?? "WO-{$workOrder->id}";
    }

    /**
     * Check if a staff member is a CRM user
     */
    protected function isCrmUser(int $staffId): bool
    {
        $staff = Staff::find($staffId);
        return $staff && $staff->has_access_in_crm == 1;
    }

    // ==========================================
    // Work Order Status Notifications
    // ==========================================

    public function workOrderCreated(WorkOrder $workOrder, int $createdBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($createdBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "New Work Order Created",
            "{$actorName} created work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $createdBy
        );
    }

    public function workOrderUpdated(WorkOrder $workOrder, int $updatedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($updatedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Updated",
            "{$actorName} updated work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $updatedBy
        );
    }

    public function workOrderAccepted(WorkOrder $workOrder, int $acceptedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($acceptedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Accepted",
            "{$actorName} accepted work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $acceptedBy
        );
    }

    public function workOrderRejected(WorkOrder $workOrder, int $rejectedBy, string $reason, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($rejectedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Rejected",
            "{$actorName} rejected work order #{$code}: {$reason}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $rejectedBy
        );
    }

    public function serviceStarted(WorkOrder $workOrder, int $startedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($startedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Service Started",
            "{$actorName} started service for #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $startedBy
        );
    }

    public function workStarted(WorkOrder $workOrder, int $startedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($startedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Started",
            "{$actorName} started working on #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $startedBy
        );
    }

    public function workOrderCompleted(WorkOrder $workOrder, int $completedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($completedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Completed",
            "{$actorName} completed work order #{$code}. Pending approval. Click to view.",
            'work-order',
            $workOrder,
            $isVendor ? null : $completedBy
        );
    }

    public function workOrderApproved(WorkOrder $workOrder, int $approvedBy): void
    {
        $actorName = $this->getActorName($approvedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Approved",
            "{$actorName} approved work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $approvedBy
        );
    }

    public function workOrderClosed(WorkOrder $workOrder, int $closedBy): void
    {
        $actorName = $this->getActorName($closedBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Closed",
            "{$actorName} closed work order #{$code}. Click to view.",
            'work-order',
            $workOrder,
            $closedBy
        );
    }

    public function workOrderCancelled(WorkOrder $workOrder, int $cancelledBy, string $reason): void
    {
        $actorName = $this->getActorName($cancelledBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Work Order Cancelled",
            "{$actorName} cancelled #{$code}: {$reason}. Click to view.",
            'work-order',
            $workOrder,
            $cancelledBy
        );
    }

    public function workOrderSentBackForRework(WorkOrder $workOrder, int $rejectedBy, string $reason): void
    {
        $actorName = $this->getActorName($rejectedBy);
        $code = $this->getWorkOrderCode($workOrder);

        // Notify assigned staff
        $this->notifyAssignedStaff(
            $workOrder,
            "Rework Required",
            "{$actorName} sent #{$code} back for rework: {$reason}. Click to view.",
            'work-order'
        );
    }

    public function partInDemand(WorkOrder $workOrder, int $markedBy, bool $isVendor = false): void
    {
        $actorName = $this->getActorName($markedBy, $isVendor);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAllStaff(
            "Part Required",
            "{$actorName} marked #{$code} as waiting for parts. Click to view.",
            'part-request',
            $workOrder,
            $isVendor ? null : $markedBy
        );
    }

    public function workOrderScheduled(WorkOrder $workOrder, int $scheduledBy, string $scheduledDate): void
    {
        $actorName = $this->getActorName($scheduledBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAssignedStaff(
            $workOrder,
            "Appointment Scheduled",
            "{$actorName} scheduled #{$code} for {$scheduledDate}. Click to view.",
            'reminder'
        );
    }

    // ==========================================
    // Assignment Notifications
    // ==========================================

    public function staffAssigned(WorkOrder $workOrder, int $assignedStaffId, int $assignedBy): void
    {
        $assignerName = $this->getActorName($assignedBy);
        $assigneeName = $this->getActorName($assignedStaffId);
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
        $reassignerName = $this->getActorName($reassignedBy);
        $oldStaffName = $this->getActorName($oldStaffId);
        $newStaffName = $this->getActorName($newStaffId);
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
        $actorName = $this->getActorName($uploadedBy);
        $code = $this->getWorkOrderCode($workOrder);
        $fileText = $fileCount === 1 ? 'file' : 'files';

        $this->notifyAllStaff(
            "Files Uploaded",
            "{$actorName} uploaded {$fileCount} {$fileText} to #{$code}. Click to view.",
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
        $actorName = $this->getActorName($addedBy);
        $code = $this->getWorkOrderCode($workOrder);
        $stars = str_repeat('⭐', $rating);

        $this->notifyAllStaff(
            "Customer Feedback",
            "{$actorName} added feedback for #{$code} ({$stars}). Click to view.",
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
        $actorName = $this->getActorName($sentBy);
        $code = $this->getWorkOrderCode($workOrder);

        $this->notifyAssignedStaff(
            $workOrder,
            "Reminder",
            "{$actorName} sent a reminder for #{$code}: {$remark}. Click to view.",
            'reminder'
        );
    }

    // ==========================================
    // Duplicate & Reopen Notifications
    // ==========================================

    public function workOrderDuplicated(WorkOrder $originalWorkOrder, WorkOrder $newWorkOrder, int $duplicatedBy): void
    {
        $actorName = $this->getActorName($duplicatedBy);
        $originalCode = $this->getWorkOrderCode($originalWorkOrder);
        $newCode = $this->getWorkOrderCode($newWorkOrder);

        $this->notifyAllStaff(
            "Work Order Duplicated",
            "{$actorName} duplicated #{$originalCode} as #{$newCode}. Click to view.",
            'work-order',
            $newWorkOrder,
            $duplicatedBy
        );
    }

    public function workOrderReopened(WorkOrder $originalWorkOrder, WorkOrder $newWorkOrder, int $reopenedBy): void
    {
        $actorName = $this->getActorName($reopenedBy);
        $originalCode = $this->getWorkOrderCode($originalWorkOrder);
        $newCode = $this->getWorkOrderCode($newWorkOrder);

        $this->notifyAllStaff(
            "Work Order Reopened",
            "{$actorName} reopened #{$originalCode} as #{$newCode}. Click to view.",
            'work-order',
            $newWorkOrder,
            $reopenedBy
        );
    }

    // ==========================================
    // Attendance Notifications
    // ==========================================

    public function staffCheckedIn(int $staffId): void
    {
        $actorName = $this->getActorName($staffId);
        $time = now()->format('h:i A');

        $this->notifyAllStaffGeneric(
            "Staff Checked In",
            "{$actorName} checked in at {$time}.",
            'attendance',
            $staffId
        );
    }

    public function staffCheckedOut(int $staffId, float $workingHours): void
    {
        $actorName = $this->getActorName($staffId);
        $time = now()->format('h:i A');
        $hours = round($workingHours, 1);

        $this->notifyAllStaffGeneric(
            "Staff Checked Out",
            "{$actorName} checked out at {$time}. Worked {$hours} hours.",
            'attendance',
            $staffId
        );
    }

    /**
     * Send notification to all active staff (generic, no work order link)
     * Filtered by branch if staff checking in/out has a branch
     */
    public function notifyAllStaffGeneric(string $title, string $message, string $type, ?int $excludeUserId = null): void
    {
        // Suppress notification if the actor is a CRM user (unlikely for attendance, but for consistency)
        if ($excludeUserId && $this->isCrmUser($excludeUserId)) {
            return;
        }

        $query = \App\Models\Staff::where('status_id', 1)
            ->where('has_access_in_crm', 1);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);

            // Filter by branch of the staff member if possible
            $actor = \App\Models\Staff::find($excludeUserId);
            if ($actor && $actor->branch_id) {
                $query->where('branch_id', $actor->branch_id);
            }
        }

        $staffMembers = $query->get();

        foreach ($staffMembers as $staff) {
            \App\Models\Notification::createNotification(
                $staff->id,
                'App\Models\Staff',
                $title,
                $message,
                $type,
                ['link' => '/crm/attendance']
            );
        }
    }
}
