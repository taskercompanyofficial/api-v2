<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAdvanceDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'advance_id',
        'salary_payout_id',
        'amount',
        'month',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the advance
     */
    public function advance()
    {
        return $this->belongsTo(StaffAdvance::class, 'advance_id');
    }

    /**
     * Get the salary payout
     */
    public function salaryPayout()
    {
        return $this->belongsTo(SalaryPayout::class);
    }
}
