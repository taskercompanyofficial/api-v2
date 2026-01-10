<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCenterUnit extends Model
{
    protected $fillable = [
        'work_order_id',
        'unit_serial_number',
        'unit_model',
        'unit_type',
        'unit_condition_on_arrival',
        'pickup_date',
        'pickup_time',
        'pickup_by',
        'pickup_location',
        'pickup_photos',
        'received_at',
        'received_by',
        'bay_number',
        'status',
        'diagnosis_notes',
        'repair_notes',
        'parts_used',
        'repair_completed_at',
        'repair_completed_by',
        'delivery_date',
        'delivery_time',
        'delivered_by',
        'delivery_photos',
        'customer_signature',
        'estimated_completion_date',
        'actual_completion_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pickup_photos' => 'array',
        'delivery_photos' => 'array',
        'parts_used' => 'array',
        'pickup_date' => 'date',
        'delivery_date' => 'date',
        'estimated_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'received_at' => 'datetime',
        'repair_completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING_PICKUP = 'pending_pickup';
    const STATUS_IN_TRANSIT_TO_CENTER = 'in_transit_to_center';
    const STATUS_RECEIVED = 'received';
    const STATUS_DIAGNOSIS = 'diagnosis';
    const STATUS_AWAITING_PARTS = 'awaiting_parts';
    const STATUS_IN_REPAIR = 'in_repair';
    const STATUS_REPAIRED = 'repaired';
    const STATUS_QUALITY_CHECK = 'quality_check';
    const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    const STATUS_IN_TRANSIT_TO_CUSTOMER = 'in_transit_to_customer';
    const STATUS_DELIVERED = 'delivered';

    /**
     * Get status labels
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING_PICKUP => 'Pending Pickup',
            self::STATUS_IN_TRANSIT_TO_CENTER => 'In Transit to Center',
            self::STATUS_RECEIVED => 'Received at Center',
            self::STATUS_DIAGNOSIS => 'Under Diagnosis',
            self::STATUS_AWAITING_PARTS => 'Awaiting Parts',
            self::STATUS_IN_REPAIR => 'In Repair',
            self::STATUS_REPAIRED => 'Repaired',
            self::STATUS_QUALITY_CHECK => 'Quality Check',
            self::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
            self::STATUS_IN_TRANSIT_TO_CUSTOMER => 'In Transit to Customer',
            self::STATUS_DELIVERED => 'Delivered',
        ];
    }

    /**
     * Get status label attribute
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'pickup_by');
    }

    public function receivedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'received_by');
    }

    public function repairedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'repair_completed_by');
    }

    public function deliveredByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'delivered_by');
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ServiceCenterUnitHistory::class)->orderBy('performed_at', 'desc');
    }

    /**
     * Log a status change or action
     */
    public function logAction(string $action, ?string $notes = null, ?int $performedBy = null, array $metadata = []): ServiceCenterUnitHistory
    {
        return $this->history()->create([
            'status' => $this->status,
            'action' => $action,
            'notes' => $notes,
            'performed_by' => $performedBy,
            'performed_at' => now(),
            'metadata' => $metadata,
        ]);
    }
}
