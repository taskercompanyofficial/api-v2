<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCenterUnitHistory extends Model
{
    protected $table = 'service_center_unit_history';

    protected $fillable = [
        'service_center_unit_id',
        'status',
        'action',
        'notes',
        'performed_by',
        'performed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'performed_at' => 'datetime',
    ];

    // Relationships

    public function serviceCenterUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterUnit::class);
    }

    public function performedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by');
    }
}
