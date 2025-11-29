<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductServicePrice extends Model
{
    protected $fillable = [
        'product_id',
        'service_id',
        'price',
        'created_by',
        'updated_by',
        'status',
        'includes',
        'notes',
    ];
    protected $casts = [
        'includes' => 'array',
    ];
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
