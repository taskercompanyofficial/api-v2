<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class VendorStaff extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory, SoftDeletes;

    protected $table = 'vendor_staff';

    protected $fillable = [
        'vendor_id',
        'name',
        'phone',
        'email',
        'password',
        'cnic',
        'cnic_front_image',
        'cnic_back_image',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
