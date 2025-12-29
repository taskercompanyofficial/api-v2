<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerBranch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'updated_by',
        'dealer_id',
        'name',
        'slug',
        'branch_code',
        'branch_designation',
        'phone',
        'whatsapp',
        'email',
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
        'monthly_target',
        'opening_hours',
        'image',
        'images',
        'status',
        'is_main_branch',
        'visible_to_customers',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'products_handled' => 'array',
            'service_areas' => 'array',
            'opening_hours' => 'array',
            'images' => 'array',
            'monthly_target' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_main_branch' => 'boolean',
            'visible_to_customers' => 'boolean',
        ];
    }

    /**
     * Get the dealer this branch belongs to.
     */
    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * Get the user who created this dealer branch.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the user who last updated this dealer branch.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
