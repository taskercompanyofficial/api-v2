<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'whatsapp_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_conversation_id',
        'whatsapp_message_id',
        'direction',
        'type',
        'content',
        'media',
        'template_data',
        'interactive_data',
        'status',
        'error_message',
        'error_code',
        'sent_by',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'media' => 'array',
        'template_data' => 'array',
        'interactive_data' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    /**
     * Get the user who sent the message (for outbound messages).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Scope a query to only include inbound messages.
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope a query to only include outbound messages.
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope a query to only include unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to filter by message type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Mark the message as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the message as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the message as failed.
     */
    public function markAsFailed(string $errorMessage, ?string $errorCode = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Check if the message is inbound.
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if the message is outbound.
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Check if the message has media.
     */
    public function hasMedia(): bool
    {
        return !empty($this->media);
    }

    /**
     * Get the media URL.
     */
    public function getMediaUrl(): ?string
    {
        return $this->media['url'] ?? null;
    }

    /**
     * Get formatted timestamp for display.
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->format('M d, Y h:i A');
    }
}
