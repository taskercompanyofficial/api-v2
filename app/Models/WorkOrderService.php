<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderService extends Model
{
    protected $fillable = [
        'work_order_id',
        'category_id',
        'service_id',
        'parent_service_id',
        'service_name',
        'service_type',
        'base_price',
        'brand_tariff_price',
        'final_price',
        'is_warranty_covered',
        'warranty_part_used',
        'status',
        'assigned_technician_id',
        'technician_notes',
        'completed_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'brand_tariff_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_warranty_covered' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_technician_id');
    }
}
