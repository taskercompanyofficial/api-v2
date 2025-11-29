<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_number',
        'registration_number',
        'make',
        'model',
        'year',
        'color',
        'engine_number',
        'chassis_number',
        'fuel_type',
        'transmission_type',
        'seating_capacity',
        'vehicle_category',
        'purchase_cost',
        'purchase_date',
        'purchase_vendor',
        'registration_date',
        'registration_expiry',
        'insurance_policy_number',
        'insurance_expiry',
        'insurance_provider',
        'current_mileage',
        'status',
        'location',
        'description',
        'photo_file',
        'registration_file',
        'insurance_file'
    ];

    protected $casts = [
        'year' => 'integer',
        'purchase_date' => 'date',
        'registration_date' => 'date',
        'registration_expiry' => 'date',
        'insurance_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'current_mileage' => 'integer',
        'seating_capacity' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(VehicleUsageLog::class);
    }
}