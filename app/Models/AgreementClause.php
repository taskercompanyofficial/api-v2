<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgreementClause extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_template_id',
        'clause_number',
        'title',
        'content',
        'language',
        'direction',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the template this clause belongs to
     */
    public function template()
    {
        return $this->belongsTo(AgreementTemplate::class, 'agreement_template_id');
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Updater relationship
     */
    public function updater()
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Scope for active clauses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
