<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'organization_name',
        'business_type',
        'contact_person',
        'email',
        'phone',
        'address',
        'facility_size',
        'services',
        'description',
        'status',
        'quoted_amount',
        'admin_notes',
    ];

    protected $casts = [
        'services' => 'array',
        'quoted_amount' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the quote
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
