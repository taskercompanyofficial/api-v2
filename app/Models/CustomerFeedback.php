<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeedback extends Model
{
    use HasFactory;
 protected $table = 'customer_feedbacks';
    protected $fillable = [
        'work_order_id',
        'customer_id',
        'rating',
        'feedback_type',
        'remarks',
        'is_positive',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_positive' => 'boolean',
    ];

    /**
     * Boot method to auto-calculate is_positive
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($feedback) {
            $feedback->is_positive = $feedback->rating >= 4;
        });
    }

    /**
     * Get the work order that owns the feedback
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the customer that owns the feedback
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the staff who created the feedback
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the staff who last updated the feedback
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
