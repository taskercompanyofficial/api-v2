<?php

namespace App\Services\WorkOrder;

use App\Models\Staff;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderHistory;
use App\Events\WorkOrderUpdated;
use App\Services\WorkOrder\WorkOrderNotificationService;
use Exception;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    protected WorkOrderNotificationService $notificationService;

    public function __construct(WorkOrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new work order
     */
    public function createWorkOrder(array $data, int $userId): WorkOrder
    {
        try {
            DB::beginTransaction();

            // Get the default status: Allocated - Just Launched
            $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')->first();
            $justLaunchedSubStatus = WorkOrderStatus::where('slug', 'just-launched')
                ->where('parent_id', $allocatedStatus?->id)
                ->first();

            // Create work order
            $workOrder = WorkOrder::create([
                'work_order_number' => WorkOrder::generateNumber(),
                'customer_id' => $data['customer_id'],
                'city_id' => $data['city_id'],
                'customer_address_id' => $data['customer_address_id'],
                'category_id' => $data['category_id'],
                'service_id' => $data['service_id'],
                'parent_service_id' => $data['parent_service_id'] ?? null,
                'service_concern_id' => $data['service_concern_id'],
                'service_sub_concern_id' => $data['service_sub_concern_id'],
                'warranty_type_id' => $data['warranty_type_id'],
                'authorized_brand_id' => $data['authorized_brand_id'],
                'brand_complaint_no' => $data['brand_complaint_no'] ?? null,
                'extra_number' => $data['extra_number'] ?? null,
                'priority' => $data['priority'],
                'customer_description' => $data['customer_description'],
                'status_id' => $allocatedStatus?->id,
                'sub_status_id' => $justLaunchedSubStatus?->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            DB::commit();

            broadcast(new WorkOrderUpdated($workOrder));

            // Send notification
            $this->notificationService->workOrderCreated($workOrder, $userId);

            return $workOrder;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Duplicate work order
     */
    public function duplicateWorkOrder(WorkOrder $originalWorkOrder, array $options, int $userId): array
    {
        $quantity = $options['quantity'] ?? 1;
        $createdIds = [];
        $createdNumbers = [];
        // Get the default status: Allocated - Just Launched
        $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')->first();
        $justLaunchedSubStatus = WorkOrderStatus::where('slug', 'just-launched')
            ->where('parent_id', $allocatedStatus?->id)
            ->first();
        for ($i = 0; $i < $quantity; $i++) {
            $newWorkOrderData = [
                'customer_id' => $originalWorkOrder->customer_id,
                'customer_address_id' => $originalWorkOrder->customer_address_id,
                'work_order_number' => WorkOrder::generateNumber(),
                'work_order_source' => $originalWorkOrder->work_order_source,
                'city_id' => $originalWorkOrder->city_id,
                'priority' => $originalWorkOrder->priority,
                'brand_complaint_no' => $originalWorkOrder->brand_complaint_no,
                'extra_number' => $originalWorkOrder->extra_number,
                'customer_description' => $originalWorkOrder->customer_description,
                'defect_description' => $originalWorkOrder->defect_description,
                'status_id' => $allocatedStatus?->id,
                'sub_status_id' => $justLaunchedSubStatus?->id,
                'created_by' => $userId,
                'updated_by' => $userId,
                'duplicated_from' => $originalWorkOrder->id,
            ];

            if ($options['copy_service_details'] ?? false) {
                $newWorkOrderData['authorized_brand_id'] = $originalWorkOrder->authorized_brand_id;
                $newWorkOrderData['branch_id'] = $originalWorkOrder->branch_id;
                $newWorkOrderData['category_id'] = $originalWorkOrder->category_id;
                $newWorkOrderData['service_id'] = $originalWorkOrder->service_id;
                $newWorkOrderData['parent_service_id'] = $originalWorkOrder->parent_service_id;
                $newWorkOrderData['service_concern_id'] = $originalWorkOrder->service_concern_id;
                $newWorkOrderData['service_sub_concern_id'] = $originalWorkOrder->service_sub_concern_id;
                $newWorkOrderData['warranty_type_id'] = $originalWorkOrder->warranty_type_id;
            }

            if ($options['copy_product_details'] ?? false) {
                $newWorkOrderData['product_indoor_model'] = $originalWorkOrder->product_indoor_model;
                $newWorkOrderData['product_outdoor_model'] = $originalWorkOrder->product_outdoor_model;
                $newWorkOrderData['indoor_serial_number'] = $originalWorkOrder->indoor_serial_number;
                $newWorkOrderData['outdoor_serial_number'] = $originalWorkOrder->outdoor_serial_number;
                $newWorkOrderData['warrenty_serial_number'] = $originalWorkOrder->warrenty_serial_number;
                $newWorkOrderData['purchase_date'] = $originalWorkOrder->purchase_date;
                $newWorkOrderData['warrenty_status'] = $originalWorkOrder->warrenty_status;
            }

            $newWorkOrder = WorkOrder::create($newWorkOrderData);
            $createdIds[] = $newWorkOrder->id;
            $createdNumbers[] = $newWorkOrder->work_order_number;

            if ($options['copy_attachments'] ?? false) {
                $files = $originalWorkOrder->files;
                foreach ($files as $file) {
                    $newWorkOrder->files()->create([
                        'file_type_id' => $file->file_type_id,
                        'file_path' => $file->file_path,
                        'file_name' => $file->file_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'uploaded_by' => $userId,
                    ]);
                }
            }

            WorkOrderHistory::log(
                workOrderId: $newWorkOrder->id,
                actionType: 'created',
                description: "Work order created as duplicate of #{$originalWorkOrder->work_order_number}" . ($quantity > 1 ? " (copy " . ($i + 1) . " of {$quantity})" : ""),
                metadata: [
                    'original_work_order_id' => $originalWorkOrder->id,
                    'original_work_order_number' => $originalWorkOrder->work_order_number,
                    'copy_number' => $i + 1,
                    'total_copies' => $quantity,
                ]
            );

            broadcast(new WorkOrderUpdated($newWorkOrder));
            // Send notification
            $this->notificationService->workOrderDuplicated($originalWorkOrder, $newWorkOrder, $userId);
        }

        $duplicateDescription = $quantity > 1
            ? "Work order duplicated {$quantity} times: " . implode(', ', array_map(fn($num) => "#{$num}", $createdNumbers))
            : "Work order duplicated to #{$createdNumbers[0]}";

        WorkOrderHistory::log(
            workOrderId: $originalWorkOrder->id,
            actionType: 'duplicated',
            description: $duplicateDescription,
            metadata: [
                'new_work_order_ids' => $createdIds,
                'new_work_order_numbers' => $createdNumbers,
                'quantity' => $quantity,
            ]
        );

        return [
            'ids' => $createdIds,
            'numbers' => $createdNumbers,
            'quantity' => $quantity,
        ];
    }

    /**
     * Reopen work order
     */
    public function reopenWorkOrder(WorkOrder $originalWorkOrder, array $data, int $userId): WorkOrder
    {
        $baseWorkOrderNumber = preg_replace('/-\d+$/', '', $originalWorkOrder->work_order_number);

        $existingReopened = WorkOrder::where('work_order_number', 'like', $baseWorkOrderNumber . '-%')
            ->orderBy('work_order_number', 'desc')
            ->first();

        $nextSuffix = 1;
        if ($existingReopened && preg_match('/-(\d+)$/', $existingReopened->work_order_number, $matches)) {
            $nextSuffix = (int)$matches[1] + 1;
        }
        $allocatedStatus = WorkOrderStatus::where('slug', 'allocated')->first();
        $justLaunchedSubStatus = WorkOrderStatus::where('slug', 'just-launched')
            ->where('parent_id', $allocatedStatus?->id)
            ->first();
        $newWorkOrderNumber = $baseWorkOrderNumber . '-' . $nextSuffix;

        $newWorkOrder = WorkOrder::create([
            'work_order_number' => $newWorkOrderNumber,
            'customer_id' => $originalWorkOrder->customer_id,
            'customer_address_id' => $originalWorkOrder->customer_address_id,
            'authorized_brand_id' => $originalWorkOrder->authorized_brand_id,
            'branch_id' => $originalWorkOrder->branch_id,
            'category_id' => $originalWorkOrder->category_id,
            'city_id' => $originalWorkOrder->city_id,
            'service_id' => $originalWorkOrder->service_id,
            'parent_service_id' => $originalWorkOrder->parent_service_id,
            'service_concern_id' => $originalWorkOrder->service_concern_id,
            'service_sub_concern_id' => $originalWorkOrder->service_sub_concern_id,
            'warranty_type_id' => $originalWorkOrder->warranty_type_id,
            'product_indoor_model' => $originalWorkOrder->product_indoor_model,
            'product_outdoor_model' => $originalWorkOrder->product_outdoor_model,
            'indoor_serial_number' => $originalWorkOrder->indoor_serial_number,
            'outdoor_serial_number' => $originalWorkOrder->outdoor_serial_number,
            'warrenty_serial_number' => $originalWorkOrder->warrenty_serial_number,
            'warrenty_status' => $originalWorkOrder->warrenty_status,
            'warrenty_end_date' => $originalWorkOrder->warrenty_end_date,
            'customer_description' => $originalWorkOrder->customer_description,
            'defect_description' => $originalWorkOrder->defect_description,
            'status_id' => $allocatedStatus->id,
            'sub_status_id' => $justLaunchedSubStatus->id,
            'priority' => $data['priority'] ?? $originalWorkOrder->priority ?? 'medium',
            'created_by' => $userId,
            'updated_by' => $userId,
            'reopened_from' => $originalWorkOrder->id,
        ]);

        WorkOrderHistory::log(
            workOrderId: $newWorkOrder->id,
            actionType: 'created',
            description: "Work order reopened from #{$originalWorkOrder->work_order_number}",
            metadata: [
                'original_work_order_id' => $originalWorkOrder->id,
                'original_work_order_number' => $originalWorkOrder->work_order_number,
                'reopen_reason' => $data['reopen_reason'] ?? 'Complaint reopened',
                'reopen_count' => $nextSuffix,
            ]
        );

        WorkOrderHistory::log(
            workOrderId: $originalWorkOrder->id,
            actionType: 'reopened',
            description: "Work order reopened as #{$newWorkOrder->work_order_number}",
            metadata: [
                'new_work_order_id' => $newWorkOrder->id,
                'new_work_order_number' => $newWorkOrder->work_order_number,
                'reopen_reason' => $data['reopen_reason'] ?? 'Complaint reopened',
            ]
        );

        broadcast(new WorkOrderUpdated($newWorkOrder));
        broadcast(new WorkOrderUpdated($originalWorkOrder));

        // Send notification
        $this->notificationService->workOrderReopened($originalWorkOrder, $newWorkOrder, $userId);

        return $newWorkOrder;
    }

    /**
     * Update work order details
     */
    public function updateWorkOrder(WorkOrder $workOrder, array $data, int $userId): WorkOrder
    {
        try {
            DB::beginTransaction();

            // Transform empty arrays/strings to null for foreign key fields
            $foreignKeys = ['authorized_brand_id', 'branch_id', 'category_id', 'service_id', 'parent_service_id', 'service_concern_id', 'service_sub_concern_id', 'city_id'];
            foreach ($foreignKeys as $key) {
                if (isset($data[$key]) && (is_array($data[$key]) || $data[$key] === '' || $data[$key] === [])) {
                    $data[$key] = null;
                }
            }

            $workOrder->fill($data);
            $workOrder->updated_by = $userId;
            $workOrder->save();

            DB::commit();

            broadcast(new WorkOrderUpdated($workOrder));

            // Send notification
            $this->notificationService->workOrderUpdated($workOrder, $userId);

            return $workOrder->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Send reminder to assigned staff
     */
    public function sendReminder(WorkOrder $workOrder, string $remark, int $userId): array
    {
        if (!$workOrder->assignedTo) {
            throw new Exception('This work order is not assigned to any staff member.');
        }

        $reminders = $workOrder->reminders ? json_decode($workOrder->reminders, true) : [];

        $reminderData = [
            'sent_at' => now()->toDateTimeString(),
            'sent_by' => $userId,
            'sent_by_name' => Staff::find($userId)->first_name . ' ' . Staff::find($userId)->last_name,
            'remark' => $remark,
            'staff_id' => $workOrder->assignedTo->id,
            'staff_name' => $workOrder->assignedTo->first_name . ' ' . $workOrder->assignedTo->last_name,
        ];

        $reminders[] = $reminderData;

        $workOrder->update([
            'reminders' => json_encode($reminders),
            'updated_by' => $userId,
        ]);

        // Send push notification
        if ($workOrder->assignedTo->device_token) {
            $notificationService = new \App\Services\NotificationService();
            $notificationService->sendPushNotification(
                $workOrder->assignedTo->device_token,
                'Work Order Reminder',
                $remark,
                [
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'type' => 'reminder',
                ]
            );
        }

        // Log reminder in history
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'reminder_sent',
            description: "Reminder sent: {$remark}",
            metadata: $reminderData
        );

        // Send real-time notification
        $this->notificationService->reminderSent($workOrder, $userId, $remark);

        return [
            'status' => 'success',
            'message' => "Reminder sent to {$workOrder->assignedTo->first_name} {$workOrder->assignedTo->last_name}",
        ];
    }
}
