<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSubConcern extends Model
{
    protected $fillable = [
        'service_concern_id',
        'name',
        'slug',
        'code',
        'description',
        'solution_guide',
        'display_order',
        'is_active'
    ];

    protected $casts = [
        'service_concern_id' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get the concern that owns this sub-concern
     */
    public function concern(): BelongsTo
    {
        return $this->belongsTo(ServiceConcern::class, 'service_concern_id');
    }

    /**
     * Get all work orders using this sub-concern
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get all file requirement rules for this sub-concern
     */
    public function fileRequirementRules(): HasMany
    {
        return $this->hasMany(FileRequirementRule::class);
    }

    /**
     * Scope: Only active sub-concerns
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by concern
     */
    public function scopeForConcern($query, $concernId)
    {
        return $query->where('service_concern_id', $concernId);
    }

    /**
     * Scope: Only sub-concerns with error codes
     */
    public function scopeWithCode($query)
    {
        return $query->whereNotNull('code');
    }

    /**
     * Scope: Order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
