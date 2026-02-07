<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAllowance extends Model
{
    protected $fillable = [
        'staff_id',
        'expense_category_id',
        'amount_per_day',
        'calculation_type',
        'percentage',
        'requires_attendance',
        'requires_crm_access',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_per_day' => 'decimal:2',
        'percentage' => 'decimal:2',
        'requires_attendance' => 'boolean',
        'requires_crm_access' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    public function weeklyExpenses(): HasMany
    {
        return $this->hasMany(StaffWeeklyExpense::class);
    }
}
