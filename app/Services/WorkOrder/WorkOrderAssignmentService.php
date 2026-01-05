<?php

namespace App\Services\WorkOrder;

use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderHistory;
use App\Models\Staff;
use Exception;
use Illuminate\Support\Facades\Log;

class WorkOrderAssignmentService
{
    /**
     * Assign or reassign staff to a work order
     */
    public function assignStaff(WorkOrder $workOrder, int $staffId, ?string $notes, int $currentUserId): array
    {
        // Prevent assignment if completed, cancelled, rejected, or closed
        // if ($workOrder->completed_at || $workOrder->cancelled_at || $workOrder->rejected_at || $workOrder->closed_at) {
        //     throw new Exception('Cannot assign staff to completed, cancelled, or closed work order');
        // }

        $previousAssignedId = $workOrder->assigned_to_id;

        // Check if it's the same staff
        if ($previousAssignedId == $staffId) {
            throw new Exception('This work order is already assigned to the selected staff member');
        }

        $assignedStaff = Staff::findOrFail($staffId);

        // Get the Dispatched - Assigned to Technician status
        $dispatchedStatus = WorkOrderStatus::where('slug', 'dispatched')->first();
        $assignedToTechnicianSubStatus = WorkOrderStatus::where('slug', 'assigned-to-technician')
            ->where('parent_id', $dispatchedStatus?->id)
            ->first();

        // Prepare update data
        $updateData = [
            'assigned_to_id' => $staffId,
            'assigned_at' => now(),
            'status_id' => $dispatchedStatus?->id,
            'sub_status_id' => $assignedToTechnicianSubStatus?->id,
            'updated_by' => $currentUserId,
        ];

        // If reassigning (not first assignment), reset all action timestamps
        if ($previousAssignedId) {
            $updateData['accepted_at'] = null;
            $updateData['rejected_at'] = null;
            $updateData['rejected_by'] = null;
            $updateData['reject_reason'] = null;
            
            // Reset service timing
            $updateData['appointment_date'] = null;
            $updateData['appointment_time'] = null;
            $updateData['service_start_date'] = null;
            $updateData['service_start_time'] = null;
            $updateData['service_end_date'] = null;
            $updateData['service_end_time'] = null;
        }

        // Update work order
        $workOrder->update($updateData);

        // Add notes to technician_remarks if provided
        if ($notes) {
            $currentRemarks = $workOrder->technician_remarks ?? '';
            $actionText = $previousAssignedId ? 'Reassigned' : 'Assigned';
            
            $newRemarks = $currentRemarks 
                ? $currentRemarks . "\n\n[{$actionText} " . now()->format('Y-m-d H:i') . " to {$assignedStaff->first_name} {$assignedStaff->last_name}]: " . $notes
                : "[{$actionText} " . now()->format('Y-m-d H:i') . " to {$assignedStaff->first_name} {$assignedStaff->last_name}]: " . $notes;
            
            $workOrder->update(['technician_remarks' => $newRemarks]);
        }

        // Log history
        $previousStaff = $previousAssignedId ? Staff::find($previousAssignedId) : null;
        $action = $previousAssignedId ? 'reassigned' : 'assigned';
        $description = $previousAssignedId 
            ? "Work order reassigned from {$previousStaff->first_name} {$previousStaff->last_name} to {$assignedStaff->first_name} {$assignedStaff->last_name}"
            : "Work order assigned to {$assignedStaff->first_name} {$assignedStaff->last_name}";

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: $action,
            description: $description,
            fieldName: 'assigned_to_id',
            oldValue: $previousStaff ? "{$previousStaff->first_name} {$previousStaff->last_name}" : null,
            newValue: "{$assignedStaff->first_name} {$assignedStaff->last_name}",
            metadata: [
                'previous_staff_id' => $previousAssignedId,
                'new_staff_id' => $staffId,
                'notes' => $notes,
                'status_changed_to' => $dispatchedStatus?->name,
                'sub_status_changed_to' => $assignedToTechnicianSubStatus?->name,
            ]
        );

        // Send Notifications
        $this->sendNotifications($workOrder, $assignedStaff);

        return [
            'status' => 'success',
            'message' => $previousAssignedId 
                ? "Work order reassigned to {$assignedStaff->first_name} {$assignedStaff->last_name} successfully"
                : "Work order assigned to {$assignedStaff->first_name} {$assignedStaff->last_name} successfully",
            'data' => $workOrder->fresh(['assignedTo', 'status', 'subStatus'])
        ];
    }

    /**
     * Send Push and WhatsApp notifications
     */
    private function sendNotifications(WorkOrder $workOrder, Staff $staff): void
    {
        try {
            $notificationService = new \App\Services\NotificationService();

            // Push Notification
            if ($staff->device_token) {
                $notificationService->sendPushNotification(
                    $staff->device_token,
                    "New Work Order Assigned",
                    "You have been assigned to Work Order #{$workOrder->work_order_number}",
                    ['work_order_id' => $workOrder->id]
                );
            }

            // WhatsApp Notification
            if ($staff->phone) {
                $message = $this->formatWorkOrderForWhatsApp($workOrder->fresh([
                    'customer', 'address', 'brand', 'category', 'service', 'parentService', 'product'
                ]));
                
                $notificationService->sendWhatsAppNotification($staff->phone, $message);
            }
        } catch (Exception $e) {
            Log::error('Failed to send notifications in AssignmentService', [
                'work_order_id' => $workOrder->id,
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format work order details for WhatsApp (moved from controller)
     */
    private function formatWorkOrderForWhatsApp(WorkOrder $workOrder): string
    {
        $lines = [];
        $lines[] = "═══════════════════════════════════════";
        $lines[] = "*WORK ORDER: {$workOrder->work_order_number}*";
        $lines[] = "*Brand Complaint #: {$workOrder->brand_complaint_no}*";
        $lines[] = "═══════════════════════════════════════";
        $lines[] = "";

        $lines[] = "*👤 CUSTOMER INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        $lines[] = "*Name:* " . ($workOrder->customer->name ?? 'N/A');
        if ($workOrder->customer->phone) $lines[] = "*Phone:* {$workOrder->customer->phone}";
        if ($workOrder->customer->whatsapp) $lines[] = "*WhatsApp:* {$workOrder->customer->whatsapp}";
        $lines[] = "";

        if ($workOrder->address) {
            $lines[] = "*📍 ADDRESS INFORMATION*";
            $lines[] = "─────────────────────────────────────";
            if ($workOrder->address->address_line_1) $lines[] = "*Address:* {$workOrder->address->address_line_1}";
            $lines[] = "";
        }

        $lines[] = "*🔧 PRODUCT INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->brand) $lines[] = "*Brand:* {$workOrder->brand->name}";
        if ($workOrder->product) $lines[] = "*Product:* {$workOrder->product->name}";
        $lines[] = "";

        $lines[] = "*⚙️ SERVICE INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->service) $lines[] = "*Service Type:* {$workOrder->service->name}";
        $lines[] = "";

        if ($workOrder->defect_description) {
            $lines[] = "*🔍 DEFECT DESCRIPTION*";
            $lines[] = "─────────────────────────────────────";
            $lines[] = $workOrder->defect_description;
            $lines[] = "";
        }

        $lines[] = "═══════════════════════════════════════";
        $lines[] = "Generated: " . now()->format('Y-m-d H:i:s');
        $lines[] = "═══════════════════════════════════════";

        return implode("\n", $lines);
    }
}
