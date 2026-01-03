<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderPart extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'part_id',
        'quantity',
        'request_type',
        'pricing_source',
        'unit_price',
        'total_price',
        'part_request_number',
        'is_returned_faulty',
        'faulty_part_returned_at',
        'status',
        'notes',
        'payment_proof_path',
        'payment_proof_uploaded_at',
        'gas_pass_slip_path',
        'gas_pass_slip_uploaded_at',
        'return_slip_path',
        'return_slip_uploaded_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_returned_faulty' => 'boolean',
        'faulty_part_returned_at' => 'datetime',
        'payment_proof_uploaded_at' => 'datetime',
        'gas_pass_slip_uploaded_at' => 'datetime',
        'return_slip_uploaded_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Get the work order that owns the part.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the catalog part definition.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Get the staff who created the request.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the staff who last updated the request.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (!$model->updated_by && auth()->check()) {
                $model->updated_by = auth()->id();
            }
            
            // Calculate total price
            $model->total_price = $model->unit_price * $model->quantity;
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
            
            // Recalculate total price
            $model->total_price = $model->unit_price * $model->quantity;
        });
    }
}
