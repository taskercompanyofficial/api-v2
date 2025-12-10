<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'staff_id',
        'leave_type_id',
        'year',
        'total_days',
        'used_days',
        'pending_days',
        'available_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'total_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'available_days' => 'decimal:2',
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
     * Update available days calculation
     */
    public function updateAvailableDays()
    {
        $this->available_days = $this->total_days - $this->used_days - $this->pending_days;
        $this->save();
    }

    /**
     * Deduct days from balance (when leave is approved)
     */
    public function deductDays(float $days)
    {
        $this->used_days += $days;
        $this->pending_days -= $days;
        $this->updateAvailableDays();
    }

    /**
     * Add days to pending (when leave is applied)
     */
    public function addPendingDays(float $days)
    {
        $this->pending_days += $days;
        $this->updateAvailableDays();
    }

    /**
     * Remove days from pending (when leave is rejected/cancelled)
     */
    public function removePendingDays(float $days)
    {
        $this->pending_days -= $days;
        $this->updateAvailableDays();
    }

    /**
     * Restore used days (when approved leave is cancelled)
     */
    public function restoreDays(float $days)
    {
        $this->used_days -= $days;
        $this->updateAvailableDays();
    }
}
