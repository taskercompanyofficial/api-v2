<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentServices extends Model
{
    protected $fillable = ['service_id', 'name', 'slug', 'description', 'image', 'images', 'price', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'tags', 'includes', 'excludes', 'notes', 'status', 'created_by', 'updated_by'];
    protected $casts = [
        'service_id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'description' => 'string',
        'image' => 'string',
        'images' => 'array',
        'price' => 'float',
        'discount' => 'float',
        'discount_type' => 'string',
        'discount_start_date' => 'date',
        'discount_end_date' => 'date',
        'tags' => 'array',
        'includes' => 'array',
        'excludes' => 'array',
        'notes' => 'string',
        'status' => 'string',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Get all order items for this parent service
     */
    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'itemable');
    }

    /**
     * Get the required file types for this parent service
     */
    public function requiredFileTypes(): HasMany
    {
        return $this->hasMany(ServiceRequiredFile::class, 'parent_service_id');
    }
}
