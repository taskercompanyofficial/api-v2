<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle WhatsApp webhook verification and incoming webhooks.
| They are publicly accessible (no authentication required).
|
*/

Route::prefix('webhooks/whatsapp')->group(function () {
    // Webhook verification (GET request from WhatsApp)
    Route::get('/', [WhatsAppWebhookController::class, 'verify']);
    
    // Webhook handler (POST request from WhatsApp)
    Route::post('/', [WhatsAppWebhookController::class, 'handle']);
});
