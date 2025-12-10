<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'title',
        'message',
        'type',
        'order_id',
        'read',
        'read_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Helper method to create notification
    public static function createNotification($customerId, $title, $message, $type = 'system', $orderId = null)
    {
        return self::create([
            'customer_id' => $customerId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'order_id' => $orderId,
        ]);
    }

    // Mark as read
    public function markAsRead()
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }
}
