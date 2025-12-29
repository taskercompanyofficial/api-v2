<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MajorClient extends Model
{
    protected $table = 'major_clients';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'tags',
        'notes',
        'status',
        'units_installed',
        'customer_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'units_installed' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
