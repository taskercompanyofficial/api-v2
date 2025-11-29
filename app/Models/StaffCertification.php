<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffCertification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'certification_name',
        'issuing_organization',
        'certification_number',
        'issue_date',
        'expiry_date',
        'has_expiry',
        'credential_url',
        'certificate_file',
        'is_verified',
        'verified_date',
        'verified_by',
        'status',
        'notes'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'verified_date' => 'date',
        'has_expiry' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}