<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    protected $fillable = [
        'phone_number',
        'otp',
        'source',
        'status',
    ];
}
