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
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(WhatsAppMessage $message, ?int $userId = null)
    {
        $this->message = $message->load(['conversation.contact']);
        $this->conversationId = $message->whatsapp_conversation_id;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];

        // Also broadcast to user's private channel if assigned
        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        return $channels;
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
                'type' => $this->message->type,
                'content' => $this->message->content,
                'direction' => $this->message->direction,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at->toISOString(),
                'contact' => [
                    'id' => $this->message->conversation->contact->id ?? null,
                    'phone_number' => $this->message->conversation->contact->phone_number ?? null,
                    'whatsapp_name' => $this->message->conversation->contact->whatsapp_name ?? null,
                ],
            ],
        ];
    }
}
