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
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Assign or reassign staff or vendor to a work order
     */
    public function assignStaff(WorkOrder $workOrder, ?int $staffId, ?int $vendorId, ?string $notes, int $currentUserId): array
    {
        // Prevent assignment if completed, cancelled, rejected, or closed
        // if ($workOrder->completed_at || $workOrder->cancelled_at || $workOrder->rejected_at || $workOrder->closed_at) {
        //     throw new Exception('Cannot assign staff to completed, cancelled, or closed work order');
        // }
        if (!$notes) {
            $notes = 'No notes provided';
        }
        $previousAssignedId = $workOrder->assigned_to_id;
        $previousVendorId = $workOrder->assigned_vendor_id;

        $assignedStaff = $staffId ? Staff::find($staffId) : null;
        $assignedVendor = $vendorId ? \App\Models\Vendor::find($vendorId) : null;

        if (!$assignedStaff && !$assignedVendor) {
            throw new Exception("Either a Staff or a Vendor must be assigned.");
        }

        // Logic to clear old assignment when switching
        if ($staffId) {
            $vendorId = null; // Clear vendor if staff is selected
        } else if ($vendorId) {
            $staffId = null; // Clear staff if vendor is selected
        }

        // Get the Dispatched - Assigned to Technician status
        $dispatchedStatus = WorkOrderStatus::where('slug', 'dispatched')->first();
        $assignedToTechnicianSubStatus = WorkOrderStatus::where('slug', 'assigned-to-technician')
            ->where('parent_id', $dispatchedStatus?->id)
            ->first();

        // Prepare update data
        $updateData = [
            'assigned_to_id' => $staffId,
            'assigned_vendor_id' => $vendorId,
            'vendor_staff_id' => null, // Reset vendor staff whenever internal assignment happens
            'assigned_at' => now(),
            'status_id' => $dispatchedStatus?->id,
            'sub_status_id' => $assignedToTechnicianSubStatus?->id,
            'updated_by' => $currentUserId,
        ];

        // If reassigning (not first assignment), reset all action timestamps
        if ($previousAssignedId || $previousVendorId) {
            $updateData['accepted_at'] = null;
            $updateData['accepted_by'] = null;
            $updateData['cancelled_at'] = null;
            $updateData['cancelled_by'] = null;
            $updateData['completed_at'] = null;
            $updateData['completed_by'] = null;
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

        $previousStaff = $previousAssignedId ? Staff::find($previousAssignedId) : null;
        $previousVendorRecord = $previousVendorId ? \App\Models\Vendor::find($previousVendorId) : null;

        $action = ($previousAssignedId || $previousVendorId) ? 'reassigned' : 'assigned';

        $oldValueStr = null;
        if ($previousStaff)
            $oldValueStr = "{$previousStaff->first_name} {$previousStaff->last_name} (Staff)";
        else if ($previousVendorRecord)
            $oldValueStr = "{$previousVendorRecord->name} (Vendor)";

        $newValueStr = null;
        if ($assignedStaff)
            $newValueStr = "{$assignedStaff->first_name} {$assignedStaff->last_name} (Staff)";
        else if ($assignedVendor)
            $newValueStr = "{$assignedVendor->name} (Vendor)";

        $description = ($previousAssignedId || $previousVendorId)
            ? "Work order reassigned from {$oldValueStr} to {$newValueStr}"
            : "Work order assigned to {$newValueStr}" . ($notes ? " with notes: {$notes}" : ' no');

        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: $action,
            description: $description,
            fieldName: $staffId ? 'assigned_to_id' : 'assigned_vendor_id',
            oldValue: $oldValueStr,
            newValue: $newValueStr,
            metadata: [
                'previous_staff_id' => $previousAssignedId,
                'new_staff_id' => $staffId,
                'previous_vendor_id' => $previousVendorId,
                'new_vendor_id' => $vendorId,
                'notes' => $notes,
                'status_changed_to' => $dispatchedStatus?->name,
                'sub_status_changed_to' => $assignedToTechnicianSubStatus?->name,
            ]
        );

        // Send Push and WhatsApp notifications
        if ($assignedStaff) {
            $this->sendNotifications($workOrder, $assignedStaff, $notes);
        } else if ($assignedVendor) {
            $this->sendVendorNotifications($workOrder, $assignedVendor, $notes);
        }

        // Send real-time notification to all staff
        if ($previousAssignedId && $staffId) {
            $this->notificationService->staffReassigned($workOrder, $previousAssignedId, $staffId, $currentUserId);
        } else if ($staffId) {
            $this->notificationService->staffAssigned($workOrder, $staffId, $currentUserId);
        }

        return [
            'status' => 'success',
            'message' => ($previousAssignedId || $previousVendorId)
                ? "Work order reassigned to {$newValueStr} successfully"
                : "Work order assigned to {$newValueStr} successfully",
        ];
    }

    /**
     * Send Push and WhatsApp notifications
     */
    private function sendNotifications(WorkOrder $workOrder, Staff $staff, string $notes): void
    {
        try {
            $notificationService = new \App\Services\NotificationService();

            // Push Notification
            if ($staff->device_token) {
                $notificationService->sendPushNotification(
                    $staff->device_token,
                    "New Work Order Assigned",
                    "You have been assigned to Work Order #{$workOrder->work_order_number} with notes: {$notes}",
                    ['work_order_id' => $workOrder->id]
                );
            }

            // WhatsApp Notification
            if ($staff->phone) {
                $message = $this->formatWorkOrderForWhatsApp($workOrder->fresh([
                    'customer',
                    'address',
                    'brand',
                    'category',
                    'service',
                    'parentService',
                    'product'
                ]));

                $notificationService->sendWhatsAppNotification($staff->phone, $message);
            }
        } catch (Exception $e) {
            // Log::error(...);
        }
    }

    private function sendVendorNotifications(WorkOrder $workOrder, \App\Models\Vendor $vendor, string $notes): void
    {
        try {
            $notificationService = new \App\Services\NotificationService();
            if ($vendor->phone) {
                $message = $this->formatWorkOrderForWhatsApp($workOrder->fresh([
                    'customer',
                    'address',
                    'brand',
                    'category',
                    'service',
                    'parentService',
                    'product'
                ]));
                $notificationService->sendWhatsAppNotification($vendor->phone, "Hello {$vendor->name},\nYou have a new work order assigned:\n\n" . $message);
            }
        } catch (Exception $e) {
            // Log error
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
        if ($workOrder->customer->phone)
            $lines[] = "*Phone:* {$workOrder->customer->phone}";
        if ($workOrder->customer->whatsapp)
            $lines[] = "*WhatsApp:* {$workOrder->customer->whatsapp}";
        $lines[] = "";

        if ($workOrder->address) {
            $lines[] = "*📍 ADDRESS INFORMATION*";
            $lines[] = "─────────────────────────────────────";
            if ($workOrder->address->address_line_1)
                $lines[] = "*Address:* {$workOrder->address->address_line_1}";
            $lines[] = "";
        }

        $lines[] = "*🔧 PRODUCT INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->brand)
            $lines[] = "*Brand:* {$workOrder->brand->name}";
        if ($workOrder->product)
            $lines[] = "*Product:* {$workOrder->product->name}";
        $lines[] = "";

        $lines[] = "*⚙️ SERVICE INFORMATION*";
        $lines[] = "─────────────────────────────────────";
        if ($workOrder->service)
            $lines[] = "*Service Type:* {$workOrder->service->name}";
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
