<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'updated_by',
        'name',
        'slug',
        'business_type',
        'license_number',
        'registration_number',
        'phone',
        'whatsapp',
        'email',
        'owner_name',
        'owner_phone',
        'contact_person_name',
        'contact_person_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip_code',
        'country',
        'area_type',
        'latitude',
        'longitude',
        'products_handled',
        'service_areas',
        'credit_limit',
        'agreement_start_date',
        'agreement_end_date',
        'commission_rate',
        'logo',
        'images',
        'documents',
        'status',
        'is_verified',
        'can_create_branches',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'products_handled' => 'array',
            'service_areas' => 'array',
            'images' => 'array',
            'documents' => 'array',
            'credit_limit' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'agreement_start_date' => 'date',
            'agreement_end_date' => 'date',
            'is_verified' => 'boolean',
            'can_create_branches' => 'boolean',
        ];
    }

    /**
     * Get the user who created this dealer.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this dealer.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all branches for this dealer.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(DealerBranch::class)->orderBy('is_main_branch', 'desc')->orderBy('name');
    }

    /**
     * Get the main branch for this dealer.
     */
    public function mainBranch(): HasMany
    {
        return $this->hasMany(DealerBranch::class)->where('is_main_branch', true);
    }
}
