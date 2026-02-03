<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class PartRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'part_id',
        'store_item_id',
        'store_item_instance_id',
        'quantity',
        'request_type',
        'unit_price',
        'total_price',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function storeItem(): BelongsTo
    {
        return $this->belongsTo(StoreItems::class, 'store_item_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(StoreItemInstance::class, 'store_item_instance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Boot logic
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_by && Auth::check()) {
                $model->created_by = Auth::id();
            }
            if (!$model->updated_by && Auth::check()) {
                $model->updated_by = Auth::id();
            }
            $model->calculateTotal();
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
            $model->calculateTotal();
        });
    }

    public function calculateTotal()
    {
        $this->total_price = number_format((float)$this->unit_price * (int)$this->quantity, 2, '.', '');
    }
}
