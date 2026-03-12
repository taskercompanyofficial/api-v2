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
        'slug',
        'first_name',
        'middle_name',
        'last_name',
        'father_name',
        'company_name',
        'cnic',
        'dob',
        'gender',
        'name',
        'phone',
        'email',
        'password',
        'profile_image',
        'cnic_front_image',
        'cnic_back_image',
        'address',
        'city',
        'state',
        'postal_code',
        'joining_date',
        'notes',
        'role_id',
        'status',
        'experience',
        'handled_categories',
    ];

    protected $casts = [
        'handled_categories' => 'array',
        'dob' => 'date',
        'joining_date' => 'date',
    ];

    protected $hidden = [
        'password',
    ];
}
