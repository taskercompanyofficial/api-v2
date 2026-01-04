<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Events\TestNotificationEvent;
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
        // // Get a recent message or create a test one
        // $message = WhatsAppMessage::with(['conversation.contact'])
        //     ->latest()
        //     ->first();

        // if (!$message) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'No messages found to broadcast',
        //     ], 404);
        // }

        // Get user ID from conversation or use authenticated user
        $userId = '1';

        // Broadcast the event
        broadcast(new TestNotificationEvent('Hellooo', $userId));

        return response()->json([
            'status' => 'success',
            'message' => 'Event broadcasted successfully',
            
        ]);
    }
}
