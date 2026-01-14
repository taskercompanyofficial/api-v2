<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStoreItem extends Model
{
    protected $fillable = [
        'work_order_id',
        'store_item_instance_id',
        'quantity_used',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the work order that this store item belongs to.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the store item instance.
     */
    public function storeItemInstance(): BelongsTo
    {
        return $this->belongsTo(StoreItemInstance::class);
    }

    /**
     * Get the staff who added this item.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the staff who last updated this item.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
