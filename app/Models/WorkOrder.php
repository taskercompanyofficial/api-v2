<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'work_order_number',
        'customer_id',
        'customer_address_id',
        'authorized_brand_id',
        'brand_complaint_no',
        'indoor_serial_number',
        'outdoor_serial_number',
        'warranty_card_serial',
        'product_model',
        'priority',
        'customer_description',
        'is_warranty_case',
        'warranty_verified',
        'warranty_expiry_date',
        'status_id',
        'sub_status_id',
        'assigned_to_id',
        'assigned_at',
        'total_amount',
        'discount',
        'final_amount',
        'payment_status',
        'completed_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_warranty_case' => 'boolean',
        'warranty_verified' => 'boolean',
        'warranty_expiry_date' => 'date',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(AuthorizedBrand::class, 'authorized_brand_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function subStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'sub_status_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function services(): HasMany
    {
        return $this->hasMany(WorkOrderService::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkOrderFile::class);
    }

    // Helper Methods
    public static function generateNumber(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', now())->count() + 1;
        return "WO-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->services()->sum('final_price');
        $this->final_amount = $this->total_amount - $this->discount;
        $this->save();
    }

    public function assignTo(Staff $staff, User $assignedBy): void
    {
        $this->update([
            'assigned_to_id' => $staff->id,
            'assigned_at' => now(),
        ]);

        // Create status history
        WorkOrderStatusHistory::create([
            'work_order_id' => $this->id,
            'from_status_id' => $this->status_id,
            'to_status_id' => $this->status_id,
            'notes' => "Assigned to {$staff->name}",
            'changed_by_id' => $assignedBy->id,
            'changed_at' => now(),
        ]);
    }

    public function updateStatus(Status $status, ?Status $subStatus = null, ?string $notes = null, User $changedBy): void
    {
        $oldStatusId = $this->status_id;
        $oldSubStatusId = $this->sub_status_id;

        $this->update([
            'status_id' => $status->id,
            'sub_status_id' => $subStatus?->id,
        ]);

        // Create status history
        WorkOrderStatusHistory::create([
            'work_order_id' => $this->id,
            'from_status_id' => $oldStatusId,
            'to_status_id' => $status->id,
            'from_sub_status_id' => $oldSubStatusId,
            'to_sub_status_id' => $subStatus?->id,
            'notes' => $notes,
            'changed_by_id' => $changedBy->id,
            'changed_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'completed_at' => now(),
        ]);
    }

    public function cancel(string $reason, User $cancelledBy): void
    {
        $this->update([
            'cancelled_at' => now(),
        ]);

        WorkOrderStatusHistory::create([
            'work_order_id' => $this->id,
            'from_status_id' => $this->status_id,
            'to_status_id' => $this->status_id,
            'notes' => "Cancelled: {$reason}",
            'changed_by_id' => $cancelledBy->id,
            'changed_at' => now(),
        ]);
    }
}
