<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'product_id',
        'slug',
        'description',
        'image',
        'images',
        'tags',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'images' => 'array',
        'tags' => 'array',
        'image' => 'string',
        'category_id' => 'integer',
        'product_id' => 'integer',
    ];
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function parentServices(): HasMany
    {
        return $this->hasMany(ParentServices::class, 'service_id');
    }
}
