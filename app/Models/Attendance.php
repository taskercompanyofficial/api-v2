<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'staff_id',
        'date',
        'check_in_time',
        'check_in_location',
        'check_out_time',
        'check_out_location',
        'working_hours',
        'status',
    ];
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
