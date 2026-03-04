<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationId;
    public array $staffIds;

    public function __construct(WhatsAppMessage $message, array $staffIds = [])
    {
        $this->message = $message;
        $this->conversationId = $message->whatsapp_conversation_id;
        $this->staffIds = $staffIds;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->staffIds as $staffId) {
            $channels[] = new PrivateChannel('App.Models.User.' . $staffId);
        }
        if (empty($channels)) {
            $channels[] = new PrivateChannel('whatsapp-messages');
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'whatsapp.message.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'whatsapp_conversation_id' => $this->conversationId,
                'status' => $this->message->status,
                'delivered_at' => $this->message->delivered_at?->toISOString(),
                'read_at' => $this->message->read_at?->toISOString(),
            ]
        ];
    }
}
