<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categories extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'images',
        'tags',
        'status',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'images' => 'array',
        'tags' => 'array',
    ];
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
        return $this->hasMany(Service::class, 'category_id');
    }
}
