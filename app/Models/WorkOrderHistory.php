<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderHistory extends Model
{
    protected $fillable = [
        'work_order_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
        'metadata',
        'description',
        'user_id',
        'user_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper method to create history entry
    public static function log(
        int $workOrderId,
        string $actionType,
        ?string $description = null,
        ?string $fieldName = null,
        $oldValue = null,
        $newValue = null,
        ?array $metadata = null
    ): self {
        $user = auth()->user();
        $request = request();

        return self::create([
            'work_order_id' => $workOrderId,
            'action_type' => $actionType,
            'field_name' => $fieldName,
            'old_value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
            'metadata' => $metadata,
            'description' => $description,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
