<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreItemInstance extends Model
{
    protected $fillable = [
        'item_instance_id',
        'complaint_number',
        'assigned_to',
        'store_item_id',
        'barcode',
        'description',
        'used_price',
        'used_date',
        'image',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'images' => 'array',
        'used_date' => 'date',
    ];

    /**
     * Get the store item associated with this instance.
     */
    public function storeItem(): BelongsTo
    {
        return $this->belongsTo(StoreItems::class, 'store_item_id');
    }

    /**
     * Get the staff member this instance is assigned to.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    /**
     * Get the staff member who created this instance.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the staff member who last updated this instance.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
