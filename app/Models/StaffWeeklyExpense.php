<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffWeeklyExpense extends Model
{
    protected $fillable = [
        'staff_id',
        'expense_category_id',
        'staff_allowance_id',
        'week_start_date',
        'week_end_date',
        'working_days',
        'days_expected',
        'days_present',
        'days_absent',
        'days_leave',
        'amount_per_day',
        'total_amount',
        'status',
        'remarks',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'amount_per_day' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function staffAllowance(): BelongsTo
    {
        return $this->belongsTo(StaffAllowance::class);
    }

    public function approvedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Calculate total amount based on days present and amount per day
     */
    public function calculateTotalAmount(): void
    {
        $this->total_amount = $this->days_present * $this->amount_per_day;
    }

    /**
     * Scope for filtering by week
     */
    public function scopeForWeek($query, $weekStartDate)
    {
        return $query->where('week_start_date', $weekStartDate);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
