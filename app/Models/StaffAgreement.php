<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffAgreement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'agreement_template_id',
        'generated_html',
        'pdf_path',
        'status',
        'employee_signed_at',
        'employee_signature_data',
        'employee_ip_address',
        'ceo_signed_at',
        'ceo_signature_data',
        'ceo_signed_by',
        'gm_signed_at',
        'gm_signature_data',
        'gm_signed_by',
        'effective_date',
        'expiry_date',
        'terminated_at',
        'termination_reason',
        'custom_fields',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'employee_signed_at' => 'datetime',
        'ceo_signed_at' => 'datetime',
        'gm_signed_at' => 'datetime',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'terminated_at' => 'date',
        'custom_fields' => 'array',
    ];

    /**
     * Get the staff member this agreement belongs to
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the template used for this agreement
     */
    public function template()
    {
        return $this->belongsTo(AgreementTemplate::class, 'agreement_template_id');
    }

    /**
     * CEO who signed
     */
    public function ceoSigner()
    {
        return $this->belongsTo(Staff::class, 'ceo_signed_by');
    }

    /**
     * GM who signed
     */
    public function gmSigner()
    {
        return $this->belongsTo(Staff::class, 'gm_signed_by');
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
     * Check if agreement is fully signed
     */
    public function isFullySigned(): bool
    {
        return $this->employee_signed_at && $this->ceo_signed_at && $this->gm_signed_at;
    }

    /**
     * Scope for active agreements
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for pending signature
     */
    public function scopePendingSignature($query)
    {
        return $query->whereIn('status', ['pending_signature', 'partially_signed']);
    }
}
