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

    /**
     * Get the store item associated with this instance.
     */
    public function storeItem(): BelongsTo
    {
        return $this->belongsTo(StoreItems::class);
    }
}
