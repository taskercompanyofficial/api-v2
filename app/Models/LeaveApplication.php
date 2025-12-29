<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    protected $fillable = [
        'staff_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'applied_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'attachments',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Get the staff member
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the leave type
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    /**
     * Scope for pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Calculate working days between start and end date
     * Excludes weekends (Saturday and Sunday)
     */
    public static function calculateWorkingDays(Carbon $startDate, Carbon $endDate): float
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Skip weekends
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Approve the leave application
     */
    public function approve($approverId)
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->save();

        // Update leave balance
        $balance = LeaveBalance::where('staff_id', $this->staff_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->deductDays($this->total_days);
        }
    }

    /**
     * Reject the leave application
     */
    public function reject($approverId, $reason = null)
    {
        $this->status = 'rejected';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->rejection_reason = $reason;
        $this->save();

        // Remove from pending balance
        $balance = LeaveBalance::where('staff_id', $this->staff_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            $balance->removePendingDays($this->total_days);
        }
    }

    /**
     * Cancel the leave application
     */
    public function cancel()
    {
        $wasApproved = $this->status === 'approved';
        $this->status = 'cancelled';
        $this->save();

        // Update leave balance
        $balance = LeaveBalance::where('staff_id', $this->staff_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->start_date->year)
            ->first();

        if ($balance) {
            if ($wasApproved) {
                $balance->restoreDays($this->total_days);
            } else {
                $balance->removePendingDays($this->total_days);
            }
        }
    }
}
