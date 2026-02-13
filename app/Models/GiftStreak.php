<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftStreak extends Model
{
    protected $fillable = [
        'customer_id',
        'last_check_in_date',
        'streak',
    ];

    protected $casts = [
        'last_check_in_date' => 'date',
        'streak' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
