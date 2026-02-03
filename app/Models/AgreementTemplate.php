<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgreementTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'purpose',
        'language',
        'direction',
        'header_html',
        'footer_html',
        'is_active',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * Get all clauses for this template
     */
    public function clauses()
    {
        return $this->hasMany(AgreementClause::class)->orderBy('display_order');
    }

    /**
     * Get all staff agreements using this template
     */
    public function staffAgreements()
    {
        return $this->hasMany(StaffAgreement::class);
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
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate HTML for this template with staff data
     */
    public function generateHtml(Staff $staff): string
    {
        $html = $this->header_html ?? '';

        // Add clauses
        $html .= '<div class="agreement-content" dir="' . $this->direction . '">';
        foreach ($this->clauses as $clause) {
            $html .= '<div class="clause" dir="' . $clause->direction . '">';
            if ($clause->clause_number) {
                $html .= '<strong>(' . $clause->clause_number . ')</strong> ';
            }
            if ($clause->title) {
                $html .= '<h4>' . $clause->title . '</h4>';
            }
            $html .= '<p>' . $this->replacePlaceholders($clause->content, $staff) . '</p>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Add footer
        $html .= $this->replacePlaceholders($this->footer_html ?? '', $staff);

        return $html;
    }

    /**
     * Replace merge fields with staff data
     */
    private function replacePlaceholders(string $content, Staff $staff): string
    {
        $replacements = [
            '{{employee_name}}' => $staff->first_name . ' ' . $staff->last_name,
            '{{employee_cnic}}' => $staff->cnic ?? '',
            '{{employee_phone}}' => $staff->phone ?? '',
            '{{employee_email}}' => $staff->email ?? '',
            '{{employee_code}}' => $staff->code ?? '',
            '{{bank_name}}' => '', // TODO: Add to staff if needed
            '{{account_title}}' => '', // TODO: Add to staff if needed
            '{{account_number}}' => '', // TODO: Add to staff if needed
            '{{current_date}}' => now()->format('d-m-Y'),
            '{{company_name}}' => 'Tasker HVACR Solution',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
