<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Part extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'part_number',
        'name',
        'slug',
        'product_id',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($part) {
            if (empty($part->slug)) {
                $part->slug = Str::slug($part->name);
            }
            if (empty($part->part_number)) {
                $part->part_number = 'PART-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the product this part belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created this part
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the user who last updated this part
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
