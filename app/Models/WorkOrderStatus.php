<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkOrderStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected $with = ['parent'];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name before creating
        static::creating(function ($status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });

        // Auto-update slug when name changes
        static::updating(function ($status) {
            if ($status->isDirty('name')) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    /**
     * Get the parent status (for sub-statuses)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkOrderStatus::class, 'parent_id');
    }

    /**
     * Get all child statuses
     */
    public function children(): HasMany
    {
        return $this->hasMany(WorkOrderStatus::class, 'parent_id')
            ->orderBy('order');
    }

    /**
     * Get work orders with this status
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'status_id');
    }

    /**
     * Get the user who created this status
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get the user who last updated this status
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Scope to get only active statuses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only parent statuses (no parent_id)
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only child statuses (has parent_id)
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Check if this is a parent status
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this is a child/sub-status
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get all descendants (children and their children recursively)
     */
    public function descendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->descendants());
        }

        return $descendants;
    }
}
