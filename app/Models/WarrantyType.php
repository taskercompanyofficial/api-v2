<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarrantyType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Scope to get only active warranty types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }

    /**
     * Get warranty types for file requirement rules
     */
    public function fileRequirementRules()
    {
        return $this->hasMany(FileRequirementRule::class, 'warranty_type_id');
    }

    /**
     * Get warranty types for work orders
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'warranty_type_id');
    }
}
