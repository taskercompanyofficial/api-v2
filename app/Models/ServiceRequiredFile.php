<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequiredFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_service_id',
        'file_type_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    /**
     * Get the parent service that owns this required file.
     */
    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    /**
     * Get the file type for this required file.
     */
    public function fileType(): BelongsTo
    {
        return $this->belongsTo(FileType::class, 'file_type_id');
    }
}
