<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatusHistory extends Model
{
    protected $fillable = [
        'work_order_id',
        'from_status_id',
        'to_status_id',
        'from_sub_status_id',
        'to_sub_status_id',
        'notes',
        'changed_by_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    // Relationships
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function fromSubStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_sub_status_id');
    }

    public function toSubStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_sub_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'changed_by_id');
    }
}
