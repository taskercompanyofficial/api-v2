<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationId;
    public array $staffIds;

    /**
     * Create a new event instance.
     * 
     * @param WhatsAppMessage $message The WhatsApp message
     * @param array $staffIds Array of staff user IDs to broadcast to
     */
    public function __construct(WhatsAppMessage $message, array $staffIds = [])
    {
        $this->message = $message->load(['conversation.contact']);
        $this->conversationId = $message->whatsapp_conversation_id;
        $this->staffIds = $staffIds;

        Log::info('WhatsAppMessageReceived event created', [
            'message_id' => $message->id,
            'staff_ids' => $staffIds,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     * Broadcasts to each staff member's private channel (same as notifications)
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to each staff member's private channel
        foreach ($this->staffIds as $staffId) {
            $channels[] = new PrivateChannel('App.Models.User.' . $staffId);
        }

        // Fallback to global channel if no staff specified
        if (empty($channels)) {
            $channels[] = new PrivateChannel('whatsapp-messages');
        }

        Log::info('WhatsAppMessageReceived broadcasting', [
            'channels' => count($channels),
        ]);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'whatsapp.message.received';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->whatsapp_conversation_id,
                'whatsapp_conversation_id' => $this->message->whatsapp_conversation_id,
                'whatsapp_message_id' => $this->message->whatsapp_message_id,
                'type' => $this->message->type,
                'content' => $this->message->content,
                'media' => $this->message->media,
                'template_data' => $this->message->template_data,
                'direction' => $this->message->direction,
                'status' => $this->message->status,
                'error_message' => $this->message->error_message,
                'sent_by' => $this->message->sent_by,
                'sent_at' => $this->message->sent_at?->toISOString(),
                'delivered_at' => $this->message->delivered_at?->toISOString(),
                'read_at' => $this->message->read_at?->toISOString(),
                'failed_at' => $this->message->failed_at?->toISOString(),
                'created_at' => $this->message->created_at->toISOString(),
                'updated_at' => $this->message->updated_at->toISOString(),
                'contact' => [
                    'id' => $this->message->conversation->contact->id ?? null,
                    'phone_number' => $this->message->conversation->contact->phone_number ?? null,
                    'whatsapp_name' => $this->message->conversation->contact->whatsapp_name ?? null,
                    'profile_picture' => $this->message->conversation->contact->profile_picture ?? null,
                ],
            ],
        ];
    }
}
