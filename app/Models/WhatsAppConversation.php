<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppConversation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'whatsapp_conversations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_contact_id',
        'customer_id',
        'whatsapp_conversation_id',
        'status',
        'last_message_at',
        'assigned_to',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the WhatsApp contact that owns the conversation.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    /**
     * Get the customer that owns the conversation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the staff member assigned to the conversation (single assignment).
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    /**
     * Get all staff members who can view/manage this conversation.
     * Uses the pivot table for many-to-many relationship.
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'whatsapp_conversation_staff')
            ->withPivot(['role', 'notifications_enabled', 'last_viewed_at'])
            ->withTimestamps();
    }

    /**
     * Get staff IDs who should receive notifications for this conversation.
     */
    public function getNotifiableStaffIds(): array
    {
        return $this->staff()
            ->wherePivot('notifications_enabled', true)
            ->pluck('users.id')
            ->toArray();
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage()
    {
        return $this->hasOne(WhatsAppMessage::class, 'whatsapp_conversation_id')->latestOfMany();
    }

    /**
     * Scope a query to only include open conversations.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include closed conversations.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope a query to only include archived conversations.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to filter by assigned staff.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Open the conversation.
     */
    public function open(): void
    {
        $this->update(['status' => 'open']);
    }

    /**
     * Close the conversation.
     */
    public function close(): void
    {
        $this->update(['status' => 'closed']);
    }

    /**
     * Archive the conversation.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Assign the conversation to a staff member.
     */
    public function assignTo(int $userId): void
    {
        $this->update(['assigned_to' => $userId]);
    }

    /**
     * Update the last message timestamp.
     */
    public function updateLastMessageTime(): void
    {
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadCountAttribute(): int
    {
        return $this->messages()
            ->where('direction', 'inbound')
            ->whereNull('read_at')
            ->count();
    }
}
