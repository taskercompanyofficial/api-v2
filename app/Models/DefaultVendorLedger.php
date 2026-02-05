<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefaultVendorLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'default_vendor_ledger';

    protected $fillable = [
        'item_type',
        'parent_service_id',
        'service_name',
        'part_name',
        'part_code',
        'unit',
        'vendor_rate',
        'cost_price',
        'revenue_share_percentage',
        'is_active',
        'effective_from',
        'effective_to',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vendor_rate' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'revenue_share_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Relationships
     */
    public function parentService()
    {
        return $this->belongsTo(\App\Models\ParentServices::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeServices($query)
    {
        return $query->where('item_type', 'service');
    }

    public function scopeParts($query)
    {
        return $query->where('item_type', 'part');
    }

    /**
     * Helper Methods
     */
    public function isService()
    {
        return $this->item_type === 'service';
    }

    public function isPart()
    {
        return $this->item_type === 'part';
    }

    public function getDisplayName()
    {
        if ($this->isService()) {
            return $this->service_name ?? $this->parentService?->name ?? 'Unknown Service';
        }
        return $this->part_name ?? 'Unknown Part';
    }

    public function getFormattedPrice()
    {
        if ($this->isService()) {
            return 'Vendor Rate: ₨' . number_format($this->vendor_rate, 2);
        }
        return 'Cost: ₨' . number_format($this->cost_price, 2) . '/' . $this->unit;
    }

    /**
     * Validation Rules
     */
    public static function validationRules($id = null)
    {
        $rules = [
            'item_type' => 'required|in:service,part',
            'is_active' => 'boolean',
            'revenue_share_percentage' => 'nullable|numeric|min:0|max:100',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ];

        // Conditional rules based on item_type
        $rules['parent_service_id'] = 'required_if:item_type,service|exists:parent_services,id';
        $rules['service_name'] = 'required_if:item_type,service|string|max:255';
        $rules['vendor_rate'] = 'required_if:item_type,service|numeric|min:0';

        $rules['part_name'] = 'required_if:item_type,part|string|max:255';
        $rules['part_code'] = 'nullable|string|max:50|unique:default_vendor_ledger,part_code,' . $id;
        $rules['unit'] = 'required_if:item_type,part|string|max:50';
        $rules['cost_price'] = 'required_if:item_type,part|numeric|min:0';

        return $rules;
    }
}
