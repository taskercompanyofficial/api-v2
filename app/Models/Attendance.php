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
        'check_in_latitude',
        'check_in_longitude',
        'check_in_photo',
        'check_out_time',
        'check_out_location',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_photo',
        'working_hours',
        'break_time',
        'status',
        'notes',
        'late_reason',
        'early_leave_reason',
        'is_manual_checkin',
        'is_manual_checkout',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'working_hours' => 'decimal:2',
        'break_time' => 'integer',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'check_out_latitude' => 'decimal:8',
        'check_out_longitude' => 'decimal:8',
        'is_manual_checkin' => 'boolean',
        'is_manual_checkout' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Calculate working hours between check-in and check-out
     */
    public function calculateWorkingHours()
    {
        if ($this->check_in_time && $this->check_out_time) {
            $diff = $this->check_in_time->diffInMinutes($this->check_out_time);
            $breakTime = $this->break_time ?? 0;
            $workingMinutes = max(0, $diff - $breakTime);
            $this->working_hours = (float) round($workingMinutes / 60, 2);
            $this->save();
        }
    }

    /**
     * Determine attendance status
     */
    public function determineStatus()
    {
        if (!$this->check_in_time) {
            $this->status = 'absent';
        } elseif (!$this->check_out_time) {
            $this->status = 'present';
        } elseif ($this->working_hours < 4) {
            $this->status = 'half_day';
        } else {
            $this->status = 'present';
        }
        $this->save();
    }
}
