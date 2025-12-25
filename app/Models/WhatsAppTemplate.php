<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'language',
        'category',
        'status',
        'components',
        'preview_text',
        'parameter_count',
        'whatsapp_template_id',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'components' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * Scope a query to only include approved templates.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * Scope a query to only include pending templates.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope a query to only include rejected templates.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'REJECTED');
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by language.
     */
    public function scopeInLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Check if the template is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * Mark the template as approved.
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark the template as rejected.
     */
    public function reject(): void
    {
        $this->update([
            'status' => 'REJECTED',
            'approved_at' => null,
        ]);
    }

    /**
     * Render the template with parameters.
     *
     * @param array $parameters
     * @return array
     */
    public function render(array $parameters = []): array
    {
        $components = $this->components;
        
        foreach ($components as &$component) {
            if (isset($component['parameters'])) {
                foreach ($component['parameters'] as $index => $param) {
                    if (isset($parameters[$index])) {
                        $component['parameters'][$index]['text'] = $parameters[$index];
                    }
                }
            }
        }
        
        return $components;
    }

    /**
     * Get the body text of the template.
     */
    public function getBodyText(): ?string
    {
        $bodyComponent = collect($this->components)->firstWhere('type', 'BODY');
        return $bodyComponent['text'] ?? null;
    }

    /**
     * Validate parameters count.
     *
     * @param array $parameters
     * @return bool
     */
    public function validateParameters(array $parameters): bool
    {
        return count($parameters) === $this->parameter_count;
    }
}
