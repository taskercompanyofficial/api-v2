<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyExpenseSummary extends Model
{
    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'expense_category_id',
        'branch_id',
        'total_staff',
        'total_amount',
        'total_days_paid',
        'status',
        'generated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OurBranch::class, 'branch_id');
    }

    public function generatedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'generated_by');
    }

    public function approvedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }
}
