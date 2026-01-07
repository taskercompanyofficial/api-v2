<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileRequirementRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'parent_service_id',
        'service_concern_id',
        'service_sub_concern_id',
        'is_warranty_case',
        'authorized_brand_id',
        'category_id',
        'file_type_id',
        'requirement_type',
        'required_if_field',
        'required_if_value',
        'display_order',
        'help_text',
        'validation_rules',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'parent_service_id' => 'integer',
        'service_concern_id' => 'integer',
        'service_sub_concern_id' => 'integer',
        'is_warranty_case' => 'boolean',
        'authorized_brand_id' => 'integer',
        'category_id' => 'integer',
        'file_type_id' => 'integer',
        'display_order' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'validation_rules' => 'array'
    ];

    // Relationships
    public function parentService(): BelongsTo
    {
        return $this->belongsTo(ParentServices::class, 'parent_service_id');
    }

    public function serviceConcern(): BelongsTo
    {
        return $this->belongsTo(ServiceConcern::class, 'service_concern_id');
    }

    public function serviceSubConcern(): BelongsTo
    {
        return $this->belongsTo(ServiceSubConcern::class, 'service_sub_concern_id');
    }

    public function authorizedBrand(): BelongsTo
    {
        return $this->belongsTo(AuthorizedBrand::class, 'authorized_brand_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function fileType(): BelongsTo
    {
        return $this->belongsTo(FileType::class, 'file_type_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter rules by context
     * Matches rules where context fields are either NULL (applies to all) or match the provided value
     */
    public function scopeForContext($query, array $context)
    {
        // Match parent_service_id
        if (isset($context['parent_service_id'])) {
            $query->where(function ($q) use ($context) {
                $q->where('parent_service_id', $context['parent_service_id'])
                    ->orWhereNull('parent_service_id');
            });
        }

        // Match service_concern_id
        if (isset($context['service_concern_id'])) {
            $query->where(function ($q) use ($context) {
                $q->where('service_concern_id', $context['service_concern_id'])
                    ->orWhereNull('service_concern_id');
            });
        }

        // Match service_sub_concern_id
        if (isset($context['service_sub_concern_id'])) {
            $query->where(function ($q) use ($context) {
                $q->where('service_sub_concern_id', $context['service_sub_concern_id'])
                    ->orWhereNull('service_sub_concern_id');
            });
        }

        // Match is_warranty_case
        if (isset($context['is_warranty_case'])) {
            $query->where(function ($q) use ($context) {
                $q->where('is_warranty_case', $context['is_warranty_case'])
                    ->orWhereNull('is_warranty_case');
            });
        }

        // Match authorized_brand_id
        if (isset($context['authorized_brand_id'])) {
            $query->where(function ($q) use ($context) {
                $q->where('authorized_brand_id', $context['authorized_brand_id'])
                    ->orWhereNull('authorized_brand_id');
            });
        }

        // Match category_id
        if (isset($context['category_id'])) {
            $query->where(function ($q) use ($context) {
                $q->where('category_id', $context['category_id'])
                    ->orWhereNull('category_id');
            });
        }

        return $query;
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Calculate specificity score for this rule
     * Higher score = more specific rule
     */
    public function getSpecificityScore(): int
    {
        $score = 0;

        if ($this->parent_service_id !== null) $score += 10;
        if ($this->service_concern_id !== null) $score += 20;
        if ($this->service_sub_concern_id !== null) $score += 30;
        if ($this->is_warranty_case !== null) $score += 5;
        if ($this->authorized_brand_id !== null) $score += 15;
        if ($this->category_id !== null) $score += 10;

        return $score;
    }
}
