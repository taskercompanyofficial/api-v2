<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'updated_by',
        'name',
        'phone',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];
}
