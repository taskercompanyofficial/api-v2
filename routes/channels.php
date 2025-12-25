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
    // User can access if they are assigned to the conversation or if they have permission
    $conversation = \App\Models\WhatsAppConversation::find($conversationId);
    
    if (!$conversation) {
        return false;
    }
    
    // Allow if user is assigned to this conversation
    if ($conversation->assigned_to === $user->id) {
        return true;
    }
    
    // Allow if user has permission to view all conversations
    // You can add role/permission checks here
    return true; // For now, allow all authenticated users
});
