<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceConcern extends Model
{
    protected $fillable = [
        'parent_service_id',
        'name',
        'slug',
        'description',
        'display_order',
        'icon',
        'is_active'
    ];

    protected $casts = [
        'parent_service_id' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get the parent service that owns this concern
     */
    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    /**
     * Get all sub-concerns for this concern
     */
    public function subConcerns(): HasMany
    {
        return $this->hasMany(ServiceSubConcern::class)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name');
    }

    /**
     * Get all work orders using this concern
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get all file requirement rules for this concern
     */
    public function fileRequirementRules(): HasMany
    {
        return $this->hasMany(FileRequirementRule::class);
    }

    /**
     * Scope: Only active concerns
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by parent service
     */
    public function scopeForParentService($query, $parentServiceId)
    {
        return $query->where('parent_service_id', $parentServiceId);
    }

    /**
     * Scope: Order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
