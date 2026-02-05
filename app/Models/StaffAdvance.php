<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'amount',
        'date',
        'type',
        'status',
        'amount_paid',
        'remaining_amount',
        'reason',
        'notes',
        'installments',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the user who approved the advance
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who created the record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the record
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all deductions for this advance
     */
    public function deductions()
    {
        return $this->hasMany(StaffAdvanceDeduction::class, 'advance_id');
    }

    /**
     * Calculate remaining amount
     */
    public function updateRemainingAmount()
    {
        $this->remaining_amount = (string)($this->amount - $this->amount_paid);
        $this->save();

        // Update status
        if ($this->remaining_amount <= 0) {
            $this->status = 'completed';
            $this->save();
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partially_paid';
            $this->save();
        }
    }
}
