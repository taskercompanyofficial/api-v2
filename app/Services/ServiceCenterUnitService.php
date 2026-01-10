<?php

namespace App\Services;

use App\Models\ServiceCenterUnit;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderHistory;
use App\Services\WorkOrder\WorkOrderNotificationService;
use Exception;
use Illuminate\Support\Facades\DB;

class ServiceCenterUnitService
{
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send a work order unit to service center
     * Creates the service center unit record and updates work order status
     */
    public function sendToServiceCenter(WorkOrder $workOrder, array $data, int $userId): ServiceCenterUnit
    {
        return DB::transaction(function () use ($workOrder, $data, $userId) {
            // Check if already has a service center unit
            if ($workOrder->serviceCenterUnit) {
                throw new Exception('This work order already has a unit in service center');
            }

            // Get the "Unit in Service Center" status
            $unitInServiceCenterStatus = WorkOrderStatus::where('slug', 'unit-in-service-center')
                ->whereNull('parent_id')
                ->first();

            if (!$unitInServiceCenterStatus) {
                throw new Exception('Status "Unit in Service Center" not found. Please create it first.');
            }

            // Get sub-status "Pending Pickup" if exists
            $pendingPickupSubStatus = WorkOrderStatus::where('slug', 'pending-pickup')
                ->where('parent_id', $unitInServiceCenterStatus->id)
                ->first();

            // Create service center unit record
            $serviceCenterUnit = ServiceCenterUnit::create([
                'work_order_id' => $workOrder->id,
                'unit_serial_number' => $data['unit_serial_number'] ?? $workOrder->indoor_serial_number,
                'unit_model' => $data['unit_model'] ?? $workOrder->product_indoor_model,
                'unit_type' => $data['unit_type'] ?? null,
                'unit_condition_on_arrival' => $data['unit_condition_on_arrival'] ?? null,
                'estimated_completion_date' => $data['estimated_completion_date'] ?? null,
                'status' => ServiceCenterUnit::STATUS_PENDING_PICKUP,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Update work order status
            $oldStatusId = $workOrder->status_id;
            $oldSubStatusId = $workOrder->sub_status_id;

            $workOrder->update([
                'status_id' => $unitInServiceCenterStatus->id,
                'sub_status_id' => $pendingPickupSubStatus?->id,
                'updated_by' => $userId,
            ]);

            // Log in service center unit history
            $serviceCenterUnit->logAction(
                'created',
                'Unit sent to service center',
                $userId,
                [
                    'work_order_id' => $workOrder->id,
                    'previous_status_id' => $oldStatusId,
                    'previous_sub_status_id' => $oldSubStatusId,
                ]
            );

            // Log in work order history
            WorkOrderHistory::log(
                workOrderId: $workOrder->id,
                actionType: 'sent_to_service_center',
                description: 'Unit sent to service center for repair',
                metadata: [
                    'service_center_unit_id' => $serviceCenterUnit->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $unitInServiceCenterStatus->id,
                ]
            );

            // Send notification
            $this->notifyServiceCenterAction($workOrder, $userId, 'created');

            return $serviceCenterUnit;
        });
    }

    /**
     * Update service center unit status
     */
    public function updateStatus(ServiceCenterUnit $unit, string $newStatus, ?string $notes, int $userId): ServiceCenterUnit
    {
        $oldStatus = $unit->status;

        $unit->update([
            'status' => $newStatus,
            'updated_by' => $userId,
        ]);

        // Update specific fields based on status
        $this->updateStatusSpecificFields($unit, $newStatus, $userId);

        // Log the status change
        $unit->logAction(
            'status_updated',
            $notes ?? "Status changed from {$oldStatus} to {$newStatus}",
            $userId,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        );

        // Update work order sub-status to match
        $this->syncWorkOrderSubStatus($unit, $newStatus, $userId);

        // Send notification
        $this->notifyServiceCenterAction($unit->workOrder, $userId, $newStatus);

        return $unit->fresh();
    }

    /**
     * Update status-specific fields
     */
    protected function updateStatusSpecificFields(ServiceCenterUnit $unit, string $status, int $userId): void
    {
        switch ($status) {
            case ServiceCenterUnit::STATUS_RECEIVED:
                $unit->update([
                    'received_at' => now(),
                    'received_by' => $userId,
                ]);
                break;

            case ServiceCenterUnit::STATUS_REPAIRED:
                $unit->update([
                    'repair_completed_at' => now(),
                    'repair_completed_by' => $userId,
                ]);
                break;

            case ServiceCenterUnit::STATUS_DELIVERED:
                $unit->update([
                    'delivery_date' => now()->toDateString(),
                    'delivery_time' => now()->toTimeString(),
                    'delivered_by' => $userId,
                    'actual_completion_date' => now()->toDateString(),
                ]);
                break;
        }
    }

    /**
     * Sync work order sub-status with service center unit status
     */
    protected function syncWorkOrderSubStatus(ServiceCenterUnit $unit, string $status, int $userId): void
    {
        $workOrder = $unit->workOrder;

        // Map service center unit status to work order sub-status slug
        $subStatusSlugMap = [
            ServiceCenterUnit::STATUS_PENDING_PICKUP => 'pending-pickup',
            ServiceCenterUnit::STATUS_IN_TRANSIT_TO_CENTER => 'in-transit-to-center',
            ServiceCenterUnit::STATUS_RECEIVED => 'received-at-center',
            ServiceCenterUnit::STATUS_DIAGNOSIS => 'under-diagnosis',
            ServiceCenterUnit::STATUS_AWAITING_PARTS => 'awaiting-parts',
            ServiceCenterUnit::STATUS_IN_REPAIR => 'in-repair',
            ServiceCenterUnit::STATUS_REPAIRED => 'repaired',
            ServiceCenterUnit::STATUS_QUALITY_CHECK => 'quality-check',
            ServiceCenterUnit::STATUS_READY_FOR_DELIVERY => 'ready-for-delivery',
            ServiceCenterUnit::STATUS_IN_TRANSIT_TO_CUSTOMER => 'in-transit-to-customer',
            ServiceCenterUnit::STATUS_DELIVERED => 'delivered',
        ];

        $subStatusSlug = $subStatusSlugMap[$status] ?? null;

        if ($subStatusSlug && $workOrder->status) {
            $subStatus = WorkOrderStatus::where('slug', $subStatusSlug)
                ->where('parent_id', $workOrder->status_id)
                ->first();

            if ($subStatus) {
                $workOrder->update([
                    'sub_status_id' => $subStatus->id,
                    'updated_by' => $userId,
                ]);
            }
        }

        // If delivered, move work order to completed status
        if ($status === ServiceCenterUnit::STATUS_DELIVERED) {
            $completedStatus = WorkOrderStatus::where('slug', 'completed')
                ->whereNull('parent_id')
                ->first();

            if ($completedStatus) {
                $workOrder->update([
                    'status_id' => $completedStatus->id,
                    'completed_at' => now(),
                    'completed_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }
    }

    /**
     * Mark unit as picked up from customer
     */
    public function markAsPickedUp(ServiceCenterUnit $unit, array $data, int $userId): ServiceCenterUnit
    {
        $unit->update([
            'pickup_date' => $data['pickup_date'] ?? now()->toDateString(),
            'pickup_time' => $data['pickup_time'] ?? now()->toTimeString(),
            'pickup_by' => $userId,
            'pickup_location' => $data['pickup_location'] ?? null,
            'pickup_photos' => $data['pickup_photos'] ?? null,
            'unit_condition_on_arrival' => $data['unit_condition'] ?? $unit->unit_condition_on_arrival,
            'updated_by' => $userId,
        ]);

        return $this->updateStatus($unit, ServiceCenterUnit::STATUS_IN_TRANSIT_TO_CENTER, 'Unit picked up from customer', $userId);
    }

    /**
     * Mark unit as received at service center
     */
    public function markAsReceived(ServiceCenterUnit $unit, array $data, int $userId): ServiceCenterUnit
    {
        $unit->update([
            'bay_number' => $data['bay_number'] ?? null,
            'updated_by' => $userId,
        ]);

        return $this->updateStatus($unit, ServiceCenterUnit::STATUS_RECEIVED, 'Unit received at service center', $userId);
    }

    /**
     * Mark unit as delivered to customer
     */
    public function markAsDelivered(ServiceCenterUnit $unit, array $data, int $userId): ServiceCenterUnit
    {
        $unit->update([
            'delivery_photos' => $data['delivery_photos'] ?? null,
            'customer_signature' => $data['customer_signature'] ?? null,
            'updated_by' => $userId,
        ]);

        return $this->updateStatus($unit, ServiceCenterUnit::STATUS_DELIVERED, 'Unit delivered to customer', $userId);
    }

    /**
     * Send notification for service center actions
     */
    protected function notifyServiceCenterAction(WorkOrder $workOrder, int $userId, string $action): void
    {
        $staffName = $this->notificationService->getStaffName($userId);
        $code = $workOrder->code ?? "WO-{$workOrder->id}";

        $messages = [
            'created' => "{$staffName} sent #{$code} unit to service center. Click to view.",
            'pending_pickup' => "{$staffName} marked #{$code} unit as pending pickup. Click to view.",
            'in_transit_to_center' => "{$staffName} picked up #{$code} unit for service center. Click to view.",
            'received' => "{$staffName} received #{$code} unit at service center. Click to view.",
            'diagnosis' => "{$staffName} started diagnosis for #{$code} unit. Click to view.",
            'awaiting_parts' => "{$staffName} marked #{$code} unit as awaiting parts. Click to view.",
            'in_repair' => "{$staffName} started repair for #{$code} unit. Click to view.",
            'repaired' => "{$staffName} completed repair for #{$code} unit. Click to view.",
            'quality_check' => "{$staffName} started quality check for #{$code} unit. Click to view.",
            'ready_for_delivery' => "{$staffName} marked #{$code} unit as ready for delivery. Click to view.",
            'in_transit_to_customer' => "{$staffName} dispatched #{$code} unit for delivery. Click to view.",
            'delivered' => "{$staffName} delivered #{$code} unit to customer. Click to view.",
        ];

        $message = $messages[$action] ?? "{$staffName} updated #{$code} service center unit. Click to view.";

        $this->notificationService->notifyAllStaff(
            "Service Center Update",
            $message,
            'service-center',
            $workOrder,
            $userId
        );
    }
}
