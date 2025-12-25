<?php

use Illuminate\Support\Facades\Broadcast;

// User private channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// WhatsApp messages channel - all authenticated users can access
Broadcast::channel('whatsapp-messages', function ($user) {
    return true; // Allow all authenticated users
});
