<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'staff_id',
        'assignment_date',
        'expected_return_date',
        'actual_return_date',
        'start_mileage',
        'end_mileage',
        'assignment_type',
        'purpose',
        'assignment_notes',
        'return_notes',
        'assigned_by',
        'status'
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
        'start_mileage' => 'integer',
        'end_mileage' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}