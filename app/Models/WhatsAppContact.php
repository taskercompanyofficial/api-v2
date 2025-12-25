<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppContact extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'phone_number',
        'whatsapp_name',
        'is_opted_in',
        'last_interaction_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_opted_in' => 'boolean',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the WhatsApp contact.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the conversations for the contact.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    /**
     * Get the active conversation for the contact.
     */
    public function activeConversation()
    {
        return $this->conversations()
            ->where('status', 'open')
            ->latest('last_message_at')
            ->first();
    }

    /**
     * Format phone number to E.164 format.
     *
     * @param string $phoneNumber
     * @return string
     */
    public static function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add + prefix if not present
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }
        
        return $cleaned;
    }

    /**
     * Opt in the contact for marketing messages.
     */
    public function optIn(): void
    {
        $this->update(['is_opted_in' => true]);
    }

    /**
     * Opt out the contact from marketing messages.
     */
    public function optOut(): void
    {
        $this->update(['is_opted_in' => false]);
    }

    /**
     * Update last interaction timestamp.
     */
    public function updateLastInteraction(): void
    {
        $this->update(['last_interaction_at' => now()]);
    }
}
