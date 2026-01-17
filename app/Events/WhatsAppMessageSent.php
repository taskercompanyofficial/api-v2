<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationId;

    /**
     * Create a new event instance.
     */
    public function __construct(WhatsAppMessage $message)
    {
        $this->message = $message->load(['conversation.contact']);
        $this->conversationId = $message->whatsapp_conversation_id;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to all authenticated users
        return [
            new PrivateChannel('whatsapp-messages'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
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
