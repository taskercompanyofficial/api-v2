<?php

namespace App\Observers;

use App\Models\WorkOrder;
use App\Models\WorkOrderHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ReflectionClass;
use ReflectionMethod;

class WorkOrderObserver
{
    /**
     * Cache for relationship mappings to avoid repeated reflection
     */
    private static ?array $relationshipCache = null;

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

            // Resolve IDs to human-readable names
            $resolvedOldValue = $this->resolveValue($field, $oldValue, $workOrder);
            $resolvedNewValue = $this->resolveValue($field, $newValue, $workOrder);

            // Generate human-readable description
            $description = $this->generateDescription($field, $resolvedOldValue, $resolvedNewValue, $workOrder);

            WorkOrderHistory::log(
                workOrderId: $workOrder->id,
                actionType: 'updated',
                description: $description,
                fieldName: $field,
                oldValue: $resolvedOldValue,
                newValue: $resolvedNewValue
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
     * Dynamically resolve ID values to human-readable names using model relationships
     */
    private function resolveValue(string $field, $value, WorkOrder $workOrder)
    {
        if (is_null($value)) {
            return null;
        }

        // Get relationship mapping for this field
        $relationshipMap = $this->getRelationshipMapping();

        if (!isset($relationshipMap[$field])) {
            // Not a foreign key field, return as-is
            return $value;
        }

        $relationshipName = $relationshipMap[$field]['relationship'];
        $modelClass = $relationshipMap[$field]['model'];

        try {
            // Find the related record
            $record = $modelClass::find($value);

            if (!$record) {
                return $value; // Return original value if not found
            }

            // Get the display name for this record
            return $this->getDisplayName($record);
        } catch (\Exception $e) {
            // If any error occurs, return the original value
            return $value;
        }
    }

    /**
     * Get display name from a model instance
     * Handles different naming conventions automatically
     */
    private function getDisplayName($model): string
    {
        if (!$model) {
            return 'Unknown';
        }

        $modelClass = get_class($model);

        // Define custom name attributes for specific models
        $customNameAttributes = [
            'App\Models\Staff' => ['first_name', 'last_name'],
            'App\Models\User' => ['name'],
            'App\Models\Customer' => ['name'],
            'App\Models\CustomerAddress' => ['address_line_1', 'city'],
        ];

        // Check if this model has custom name attributes
        if (isset($customNameAttributes[$modelClass])) {
            $attributes = $customNameAttributes[$modelClass];
            $nameParts = [];

            foreach ($attributes as $attr) {
                if (isset($model->$attr) && $model->$attr) {
                    $nameParts[] = $model->$attr;
                }
            }

            return implode(' ', $nameParts) ?: 'Unknown';
        }

        // Default: try common name attributes in order of preference
        $commonNameAttributes = ['name', 'title', 'label', 'code', 'number'];

        foreach ($commonNameAttributes as $attr) {
            if (isset($model->$attr) && $model->$attr) {
                return $model->$attr;
            }
        }

        // Fallback to ID
        return (string) $model->id;
    }

    /**
     * Build relationship mapping by inspecting WorkOrder model
     * Maps foreign key fields to their relationship methods
     */
    private function getRelationshipMapping(): array
    {
        // Return cached mapping if available
        if (self::$relationshipCache !== null) {
            return self::$relationshipCache;
        }

        // Manually define the mapping to avoid reflection issues
        // This is still better than the old approach because it's centralized
        // and uses the actual relationship methods
        $mapping = [
            'customer_id' => ['relationship' => 'customer', 'model' => \App\Models\Customer::class],
            'customer_address_id' => ['relationship' => 'address', 'model' => \App\Models\CustomerAddress::class],
            'authorized_brand_id' => ['relationship' => 'brand', 'model' => \App\Models\AuthorizedBrand::class],
            'branch_id' => ['relationship' => 'branch', 'model' => \App\Models\OurBranch::class],
            'category_id' => ['relationship' => 'category', 'model' => \App\Models\Categories::class],
            'service_id' => ['relationship' => 'service', 'model' => \App\Models\Service::class],
            'parent_service_id' => ['relationship' => 'parentService', 'model' => \App\Models\ParentServices::class],
            'product_id' => ['relationship' => 'product', 'model' => \App\Models\Product::class],
            'status_id' => ['relationship' => 'status', 'model' => \App\Models\WorkOrderStatus::class],
            'sub_status_id' => ['relationship' => 'subStatus', 'model' => \App\Models\WorkOrderStatus::class],
            'assigned_to_id' => ['relationship' => 'assignedTo', 'model' => \App\Models\Staff::class],
            'dealer_id' => ['relationship' => 'dealer', 'model' => \App\Models\Dealer::class],
            'dealer_branch_id' => ['relationship' => 'dealerBranch', 'model' => \App\Models\DealerBranch::class],
            'created_by' => ['relationship' => 'createdBy', 'model' => \App\Models\Staff::class],
            'updated_by' => ['relationship' => 'updatedBy', 'model' => \App\Models\Staff::class],
            'completed_by' => ['relationship' => 'completedBy', 'model' => \App\Models\Staff::class],
            'closed_by' => ['relationship' => 'closedBy', 'model' => \App\Models\Staff::class],
            'cancelled_by' => ['relationship' => 'cancelledBy', 'model' => \App\Models\Staff::class],
            'rejected_by' => ['relationship' => 'rejectedBy', 'model' => \App\Models\Staff::class],
        ];

        // Cache the mapping
        self::$relationshipCache = $mapping;

        return $mapping;
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
            'customer_id' => 'Customer',
            'customer_address_id' => 'Customer Address',
            'customer_description' => 'Customer Description',
            'technical_description' => 'Technical Description',
            'technician_remarks' => 'Technician Remarks',
            'defect_description' => 'Defect Description',
            'service_description' => 'Service Description',
            'authorized_brand_id' => 'Brand',
            'branch_id' => 'Branch',
            'category_id' => 'Category',
            'service_id' => 'Service',
            'parent_service_id' => 'Parent Service',
            'product_id' => 'Product',
            'dealer_id' => 'Dealer',
            'dealer_branch_id' => 'Dealer Branch',
            'is_locked' => 'Lock Status',
            'is_warranty_case' => 'Warranty Case',
            'warranty_verified' => 'Warranty Verified',
            'scheduled_at' => 'Scheduled Date',
            'completed_at' => 'Completion Date',
            'cancelled_at' => 'Cancellation Date',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'completed_by' => 'Completed By',
            'closed_by' => 'Closed By',
            'cancelled_by' => 'Cancelled By',
            'rejected_by' => 'Rejected By',
        ];

        $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));

        // Handle special cases
        if ($field === 'is_locked') {
            return $newValue ? "Work order was locked" : "Work order was unlocked";
        }

        if ($field === 'is_warranty_case') {
            return $newValue ? "Marked as warranty case" : "Removed warranty case flag";
        }

        if ($field === 'warranty_verified') {
            return $newValue ? "Warranty verified" : "Warranty verification removed";
        }

        if ($field === 'priority') {
            return "Priority changed from " . strtoupper($oldValue ?? 'none') . " to " . strtoupper($newValue);
        }

        // For fields that were resolved to names, show the change
        if (str_ends_with($field, '_id') || in_array($field, ['created_by', 'updated_by', 'completed_by', 'closed_by', 'cancelled_by', 'rejected_by'])) {
            $oldDisplay = $oldValue ?? 'None';
            $newDisplay = $newValue ?? 'None';

            if ($oldValue === $newValue) {
                return "{$label} was updated";
            }

            return "{$label} changed from \"{$oldDisplay}\" to \"{$newDisplay}\"";
        }

        return "{$label} was changed";
    }
}
