<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorStaff extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vendor_staff';

    protected $fillable = [
        'vendor_id',
        'name',
        'phone',
        'cnic',
        'cnic_front_image',
        'cnic_back_image',
        'status',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
