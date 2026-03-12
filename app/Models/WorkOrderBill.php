<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderBill extends Model
{
    protected $fillable = [
        'work_order_id',
        'document_type',
        'reference',
        'date',
        'due_date',
        'data',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'payable_amount',
        'paid_amount',
        'balance_due',
        'total_expense',
        'total_profit',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_expense' => 'decimal:2',
        'total_profit' => 'decimal:2',
    ];

    // Relationships
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class, 'updated_by');
    }

    // Boot method to auto-fill audit fields
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    // Helper to generate reference number
    public static function generateReference(string $type, string $workOrderNumber): string
    {
        $prefix = $type === 'invoice' ? 'INV' : 'QT';
        $date = now()->format('dmy');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "{$prefix}-{$workOrderNumber}-{$date}-{$count}";
    }

    /**
     * Calculate totals based on breakdown data
     */
    public function calculateTotals(): void
    {
        $data = $this->data;
        if (!$data || !isset($data['items']) || !is_array($data['items'])) {
            return;
        }

        $totalExpense = 0;
        foreach ($data['items'] as $item) {
            $quantity = (float)($item['quantity'] ?? 1);
            $expense = (float)($item['expense'] ?? 0);
            $totalExpense += ($expense * $quantity);
        }

        $this->total_expense = $totalExpense;
        $this->total_profit = $this->payable_amount - $totalExpense;
    }
}
