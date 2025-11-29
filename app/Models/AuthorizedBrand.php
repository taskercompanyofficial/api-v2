<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizedBrand extends Model
{
    protected $table = 'authorized_brands';

    protected $fillable = [
        'name',
        'slug',
        'service_type',
        'status',
        'is_authorized',
        'is_available_for_warranty',
        'has_free_installation_service',
        'billing_date',
        'logo_image',
        'images',
        'documents',
        'warranty_parts',
        'service_charges',
        'materials',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_authorized' => 'boolean',
        'is_available_for_warranty' => 'boolean',
        'has_free_installation_service' => 'boolean',
        'billing_date' => 'date',
        'images' => 'array',
        'documents' => 'array',
        'warranty_parts' => 'array',
        'service_charges' => 'array',
        'materials' => 'array',
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
