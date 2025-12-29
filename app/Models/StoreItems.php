<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreItems extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'low_stock_threshold',
        'status',
        'images',
        'created_by',
        'updated_by',
        'slug',
        'sku',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'float',
        'status' => 'string',
        'low_stock_threshold' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Get the user who created the store item.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
    /**
     * Get the user who updated the store item.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
    /**
     * Get the item instances for the store item.
     */
    public function itemInstances(): HasMany
    {
        return $this->hasMany(StoreItemInstance::class, 'store_item_id');
    }
}
