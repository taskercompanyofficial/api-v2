<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizedBrand extends Model
{
    protected $table = 'authorized_brands';

    protected $fillable = [
        'name', 'slug', 'service_type', 'logo_image', 'policy_image', 'images', 'tariffs',
        'jobsheet_file', 'bill_format_file', 'billing_date', 'status',
        'is_authorized', 'is_available_for_warranty', 'has_free_installation_service',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'images' => 'array',
        'tariffs' => 'array',
        'is_authorized' => 'boolean',
        'is_available_for_warranty' => 'boolean',
        'has_free_installation_service' => 'boolean',
        'billing_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}