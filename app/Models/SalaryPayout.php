<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayout extends Model
{
    protected $fillable = [
        'staff_id',
        'month',
        'base_salary',
        'daily_rate',
        'total_days',
        'effective_days',
        'relief_absents',
        'manual_deduction',
        'advance_adjustment',
        'notes',
        'calculated_deduction',
        'final_payable',
        'paid_amount',
        'status',
        'transaction_id',
        'payment_proof',
        'paid_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'base_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'manual_deduction' => 'decimal:2',
        'advance_adjustment' => 'decimal:2',
        'calculated_deduction' => 'decimal:2',
        'final_payable' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'effective_days' => 'decimal:2',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
