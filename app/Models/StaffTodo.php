<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTodo extends Model
{
    protected $fillable = [
        'staff_id',
        'title',
        'description',
        'due_date',
        'due_time',
        'status',
        'priority',
        'remind_before',
        'reminder_at',
        'is_reminded',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_at' => 'datetime',
        'is_reminded' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Calculate and set reminder_at based on due_date + due_time + remind_before.
     */
    public function calculateReminderAt(): void
    {
        if (!$this->due_date || !$this->remind_before) {
            $this->reminder_at = null;
            return;
        }

        /** @var \Carbon\Carbon $deadline */
        $deadline = clone $this->due_date;

        // Attach time if present
        if ($this->due_time) {
            $parts = explode(':', $this->due_time);
            $deadline->setTime((int) $parts[0], (int) $parts[1]);
        } else {
            $deadline->setTime(23, 59); // end of day default
        }

        // Subtract remind period
        $this->reminder_at = match ($this->remind_before) {
            '5_min' => $deadline->subMinutes(5),
            '15_min' => $deadline->subMinutes(15),
            '30_min' => $deadline->subMinutes(30),
            '1_hour' => $deadline->subHour(),
            '2_hour' => $deadline->subHours(2),
            '1_day' => $deadline->subDay(),
            default => null,
        };
    }
}
