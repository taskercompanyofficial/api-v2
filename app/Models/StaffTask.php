<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTask extends Model
{
    protected $fillable = [
        'staff_id',
        'assigned_by',
        'title',
        'description',
        'due_date',
        'status',
        'priority',
        'category',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The staff member this task is assigned to.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * The admin/staff who assigned this task.
     */
    public function assignedBy()
    {
        return $this->belongsTo(Staff::class, 'assigned_by');
    }

    /**
     * Mark the task as completed by the staff member.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the task as in progress.
     */
    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
        ]);
    }
}
