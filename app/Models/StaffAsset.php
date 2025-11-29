<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_assets';

    protected $fillable = [
        'staff_id',
        'asset_type',
        'asset_name',
        'asset_model',
        'asset_serial',
        'asset_tag',
        'manufacturer',
        'purchase_cost',
        'purchase_date',
        'vendor',
        'condition',
        'status',
        'assigned_date',
        'return_date',
        'expected_return_date',
        'assigned_by',
        'assignment_notes',
        'photo_file',
        'receipt_file',
        'warranty_expiry',
        'is_returnable',
        'return_condition'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'assigned_date' => 'date',
        'return_date' => 'date',
        'expected_return_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'is_returnable' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}