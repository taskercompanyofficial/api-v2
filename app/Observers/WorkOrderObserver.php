<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;

class WorkOrderObserver
{
    /**
     * Handle the WorkOrder "created" event.
     */
    public function created(WorkOrder $workOrder): void
    {
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'created',
            description: "Work order {$workOrder->work_order_number} was created"
        );
    }

    /**
     * Handle the WorkOrder "updated" event.
     */
    public function updated(WorkOrder $workOrder): void
    {
        $changes = $workOrder->getChanges();
        $original = $workOrder->getOriginal();

        // Skip if only timestamps changed
        unset($changes['updated_at'], $changes['created_at']);
        
        if (empty($changes)) {
            return;
        }

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;
            
            // Skip if values are the same
            if ($oldValue == $newValue) {
                continue;
            }

            // Generate human-readable description
            $description = $this->generateDescription($field, $oldValue, $newValue, $workOrder);

            WorkOrderHistory::log(
                workOrderId: $workOrder->id,
                actionType: 'updated',
                description: $description,
                fieldName: $field,
                oldValue: $oldValue,
                newValue: $newValue
            );
        }
    }

    /**
     * Handle the WorkOrder "deleted" event.
     */
    public function deleted(WorkOrder $workOrder): void
    {
        WorkOrderHistory::log(
            workOrderId: $workOrder->id,
            actionType: 'deleted',
            description: "Work order {$workOrder->work_order_number} was deleted"
        );
    }

    /**
     * Generate human-readable description for field changes
     */
    private function generateDescription(string $field, $oldValue, $newValue, WorkOrder $workOrder): string
    {
        $fieldLabels = [
            'status_id' => 'Status',
            'sub_status_id' => 'Sub Status',
            'priority' => 'Priority',
            'assigned_to_id' => 'Assigned To',
            'customer_description' => 'Customer Description',
            'technical_description' => 'Technical Description',
            'authorized_brand_id' => 'Brand',
            'branch_id' => 'Branch',
            'category_id' => 'Category',
            'service_id' => 'Service',
            'parent_service_id' => 'Parent Service',
            'product_id' => 'Product',
            'is_locked' => 'Lock Status',
            'scheduled_at' => 'Scheduled Date',
            'completed_at' => 'Completion Date',
            'cancelled_at' => 'Cancellation Date',
        ];

        $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));

        // Handle special cases
        if ($field === 'is_locked') {
            return $newValue ? "Work order was locked" : "Work order was unlocked";
        }

        if ($field === 'priority') {
            return "Priority changed from " . strtoupper($oldValue ?? 'none') . " to " . strtoupper($newValue);
        }

        if (str_ends_with($field, '_id')) {
            return "{$label} was updated";
        }

        return "{$label} was changed";
    }
}
