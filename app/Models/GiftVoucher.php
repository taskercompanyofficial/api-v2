<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftVoucher extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'title',
        'discount_percent',
        'amount',
        'status',
        'claimed_at',
        'redeemed_at',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'amount' => 'decimal:2',
        'claimed_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
