<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appearance extends Model
{
    protected $fillable = [
        'id',
        'theme',
        'primary_color',
        'sidebar_color',
        'radius',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'primary_color' => 'string',
        'sidebar_color' => 'string',
        'radius' => 'string',
        'created_by' => 'integer',
        'updated_by' => 'integer',
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
