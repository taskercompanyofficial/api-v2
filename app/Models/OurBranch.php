<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OurBranch extends Model
{
    protected $table = 'our_branches';

    protected $fillable = [
        'name', 'slug', 'phone', 'whatsapp', 'email', 'address', 'city', 'state', 'country',
        'latitude', 'longitude', 'manager_id', 'branch_designation', 'status', 'visible_to_customers',
        'image', 'images', 'product_services', 'opening_hours', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'visible_to_customers' => 'string',
        'images' => 'array',
        'product_services' => 'array',
        'opening_hours' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_id');
    }
}