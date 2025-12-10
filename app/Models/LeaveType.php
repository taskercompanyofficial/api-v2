<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'days_per_year',
        'requires_approval',
        'is_paid',
        'color',
        'is_active',
    ];

    protected $casts = [
        'days_per_year' => 'decimal:2',
        'requires_approval' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all leave balances for this leave type
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get all leave applications for this leave type
     */
    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    /**
     * Scope to get only active leave types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
