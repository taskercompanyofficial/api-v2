<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderFile extends Model
{
    /**
     * Relationships to always load
     */
    protected $with = ['fileType', 'uploadedBy'];

    protected $fillable = [
        'work_order_id',
        'file_type_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size_kb',
        'mime_type',
        'uploaded_by_id',
        'uploaded_at',
        'notes',
        'approval_status',
        'approval_remark',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    // Relationships
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'uploaded_by_id');
    }

    public function fileType(): BelongsTo
    {
        return $this->belongsTo(FileType::class, 'file_type_id');
    }

    // Helper Methods
    public function getFileUrl(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
