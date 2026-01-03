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
        // Basic Info
        'work_order_number',
        'customer_id',
        'customer_address_id',
        
        // Foreign Keys
        'authorized_brand_id',
        'branch_id',
        'category_id',
        'service_id',
        'parent_service_id',
        'product_id',
        
        // Work Order Details
        'brand_complaint_no',
        'work_order_source',
        'priority',
        'reject_reason',
        'satisfation_code',
        'without_satisfaction_code_reason',
        
        // Service Timing
        'appointment_date',
        'appointment_time',
        'service_start_date',
        'service_start_time',
        'service_end_date',
        'service_end_time',
        
        // Descriptions
        'customer_description',
        'defect_description',
        'technician_remarks',
        'service_description',
        
        // Product Information
        'product_indoor_model',
        'product_outdoor_model',
        'indoor_serial_number',
        'outdoor_serial_number',
        'warrenty_serial_number',
        'product_model',
        'purchase_date',
        
        // Warranty Information
        'is_warranty_case',
        'warranty_verified',
        'warranty_expiry_date',
        'warrenty_start_date',
        'warrenty_end_date',
        'warrenty_status',
        'warranty_card_serial',
        
        // Status & Assignment
        'status_id',
        'sub_status_id',
        'assigned_to_id',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'rejected_by',
        
        // Financial
        'total_amount',
        'discount',
        'final_amount',
        'payment_status',
        
        // Completion & Tracking
        'completed_at',
        'completed_by',
        'closed_at',
        'closed_by',
        'cancelled_at',
        'cancelled_by',
        
        // Lock Fields
        'is_locked',
        'locked_reason',
        
        // Reminders
        'reminders',
        
        // Audit
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        // Boolean
        'is_warranty_case' => 'boolean',
        'warranty_verified' => 'boolean',
        'is_locked' => 'boolean',
        
        // Dates
        'warranty_expiry_date' => 'date',
        'warrenty_start_date' => 'date',
        'warrenty_end_date' => 'date',
        'purchase_date' => 'date',
        'appointment_date' => 'date',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
        
        // DateTime
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        
        // Decimal
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Validate status and sub_status relationship before saving
        static::saving(function ($workOrder) {
            // If both status and sub_status are set, validate they match
            if ($workOrder->status_id && $workOrder->sub_status_id) {
                $subStatus = WorkOrderStatus::find($workOrder->sub_status_id);
                
                // Sub-status must have a parent_id (be a child status)
                if ($subStatus && is_null($subStatus->parent_id)) {
                    throw new \InvalidArgumentException(
                        "Sub-status must be a child status, not a parent status."
                    );
                }
                
                // Sub-status parent must match the selected status
                if ($subStatus && $subStatus->parent_id !== $workOrder->status_id) {
                    throw new \InvalidArgumentException(
                        "Sub-status does not belong to the selected status. Please select a valid sub-status."
                    );
                }
            }
            
            // If only sub_status is set without status, set the parent as status
            if (!$workOrder->status_id && $workOrder->sub_status_id) {
                $subStatus = WorkOrderStatus::find($workOrder->sub_status_id);
                if ($subStatus && $subStatus->parent_id) {
                    $workOrder->status_id = $subStatus->parent_id;
                }
            }
        });
    }

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(OurBranch::class, 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'status_id');
    }

    public function subStatus(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'sub_status_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
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

    public function histories(): HasMany
    {
        return $this->hasMany(WorkOrderHistory::class)->orderBy('created_at', 'desc');
    }

    // Helper Methods
    public static function generateNumber(): string
    {
        $date = now()->format('dmY');
        $count = self::whereDate('created_at', now())->count() + 1;
        return "TC{$date}" . str_pad($count, 4, '0', STR_PAD_LEFT);
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

    public function updateStatus(WorkOrderStatus $status, ?WorkOrderStatus $subStatus = null, ?string $notes = null, User $changedBy): void
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
