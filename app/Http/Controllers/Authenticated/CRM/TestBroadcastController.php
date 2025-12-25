<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Events\WhatsAppMessageReceived;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TestBroadcastController extends Controller
{
    /**
     * Test broadcasting a message event
     */
    public function testMessageBroadcast(Request $request)
    {
        // Get a recent message or create a test one
        $message = WhatsAppMessage::with(['conversation.contact'])
            ->latest()
            ->first();

        if (!$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'No messages found to broadcast',
            ], 404);
        }

        // Get user ID from conversation or use authenticated user
        $userId = $message->conversation->assigned_to ?? auth()->id();

        // Broadcast the event
        broadcast(new WhatsAppMessageReceived($message, $userId));

        return response()->json([
            'status' => 'success',
            'message' => 'Event broadcasted successfully',
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $message->whatsapp_conversation_id,
                'user_id' => $userId,
                'channels' => [
                    'conversation.' . $message->whatsapp_conversation_id,
                    $userId ? 'user.' . $userId : null,
                ],
            ],
        ]);
    }
}
