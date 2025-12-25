<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class BusinessPhoneNumber extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'api_token_id',
        'phone_number',
        'phone_number_id',
        'business_account_id',
        'display_name',
        'platform',
        'status',
        'verification_code',
        'verified_at',
        'capabilities',
        'metadata',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'capabilities' => 'array',
        'metadata' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Get the API token that owns the phone number.
     */
    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }

    /**
     * Scope a query to only include active phone numbers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include verified phone numbers.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope a query to filter by platform.
     */
    public function scopeOfPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include default phone numbers.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default WhatsApp phone number.
     */
    public static function getDefaultWhatsApp(): ?self
    {
        return self::active()
            ->verified()
            ->ofPlatform('whatsapp')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Check if the phone number is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Check if the phone number is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Mark the phone number as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'status' => 'active',
            'verified_at' => now(),
            'verification_code' => null,
        ]);
    }

    /**
     * Activate the phone number.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the phone number.
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Suspend the phone number.
     */
    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    /**
     * Set as default phone number for the platform.
     */
    public function setAsDefault(): void
    {
        // Remove default from other numbers of the same platform
        self::where('platform', $this->platform)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get capability value by key.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value by key.
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get the access token for this phone number.
     */
    public function getAccessToken(): ?string
    {
        if (!$this->apiToken || !$this->apiToken->isValid()) {
            return null;
        }

        return $this->apiToken->decrypted_token;
    }
}
