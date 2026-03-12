<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorSpecificRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'item_type',
        'parent_service_id',
        'part_code',
        'vendor_rate',
        'revenue_share_percentage',
        'is_active',
    ];

    protected $casts = [
        'vendor_rate' => 'decimal:2',
        'revenue_share_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function parentService()
    {
        return $this->belongsTo(ParentServices::class);
    }
}
