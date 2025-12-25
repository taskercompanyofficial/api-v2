<?php

use Illuminate\Support\Facades\Broadcast;

// User private channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User private channel (alternative format)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Conversation private channel
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\WhatsAppConversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return true;
});
