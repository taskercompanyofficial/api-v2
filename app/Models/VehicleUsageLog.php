<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleUsageLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vehicle_usage_logs';

    protected $fillable = [
        'vehicle_id',
        'staff_id',
        'assignment_id',
        'usage_date',
        'start_time',
        'end_time',
        'start_mileage',
        'end_mileage',
        'distance_traveled',
        'purpose',
        'route_description',
        'start_location',
        'end_location',
        'fuel_consumed',
        'fuel_cost',
        'fuel_type',
        'usage_type',
        'notes',
        'is_approved',
        'approved_by'
    ];

    protected $casts = [
        'usage_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'fuel_consumed' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'distance_traveled' => 'integer',
        'start_mileage' => 'integer',
        'end_mileage' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function assignment()
    {
        return $this->belongsTo(VehicleAssignment::class);
    }
}