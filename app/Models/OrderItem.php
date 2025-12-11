<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'parent_service_id',
        'itemable_id',
        'itemable_type',
        'quantity',
        'unit_price',
        'discount',
        'discount_type',
        'subtotal',
        'scheduled_date',
        'scheduled_time',
        'status',
        'assigned_technician_id',
        'assigned_at',
        'started_at',
        'completed_at',
        'completion_notes',
        'completion_images',
        'rating',
        'feedback',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_images' => 'array',
        'rating' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    /**
     * Get the owning itemable model (ParentService, FreeInstallation, etc.)
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /**
     * Calculate subtotal based on quantity, price, and discount
     */
    public function calculateSubtotal(): void
    {
        $priceAfterDiscount = $this->unit_price;

        if ($this->discount > 0) {
            if ($this->discount_type === 'percentage') {
                $priceAfterDiscount = $this->unit_price * (1 - $this->discount / 100);
            } else {
                $priceAfterDiscount = $this->unit_price - $this->discount;
            }
        }

        $this->subtotal = $priceAfterDiscount * $this->quantity;
    }

    /**
     * Boot method to auto-calculate subtotal
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($orderItem) {
            $orderItem->calculateSubtotal();
        });
    }

    /**
     * Get service name for display
     */
    public function getServiceNameAttribute()
    {
        // Check if itemable relationship is loaded
        if ($this->relationLoaded('itemable') && $this->itemable) {
            if ($this->itemable instanceof \App\Models\FreeInstallation) {
                return $this->itemable->display_name;
            } elseif ($this->itemable instanceof \App\Models\ParentServices) {
                return $this->itemable->name;
            }
        }
        
        // Fallback to parent service if loaded
        if ($this->relationLoaded('parentService') && $this->parentService) {
            return $this->parentService->name;
        }
        
        return 'Service';
    }

    /**
     * Get price text for display
     */
    public function getPriceTextAttribute()
    {
        // Check if itemable is loaded and is FreeInstallation
        if ($this->relationLoaded('itemable') && $this->itemable instanceof \App\Models\FreeInstallation) {
            return 'Free Installation';
        }
        
        if ($this->subtotal == 0 && $this->unit_price == 0) {
            return 'Free Installation';
        }
        
        return 'Rs ' . number_format($this->subtotal, 0);
    }
}
