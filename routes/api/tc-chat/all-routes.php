<?php

use App\Http\Controllers\Auth\TcChat\AuthenticatedSessionController;
use App\Http\Controllers\Authenticated\CRM\WhatsAppController;
use App\Http\Controllers\Authenticated\StaffApp\NotificationController;
use App\Http\Controllers\Authenticated\StaffApp\StaffChatController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'tc-chat'], function () {
    Route::post('/auth/sign-in', [AuthenticatedSessionController::class, 'store']);
    Route::post('/auth/sign-out', [AuthenticatedSessionController::class, 'destroy']);
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::get('/me', [AuthenticatedSessionController::class, 'me']);
        Route::post('/notifications/register-token', [NotificationController::class, 'registerToken']);
        Route::prefix('whatsapp')->group(function () {
            Route::get('/conversations', [WhatsAppController::class, 'index']);
            Route::post('/conversations', [WhatsAppController::class, 'store']);
            Route::get('/conversations/{id}', [WhatsAppController::class, 'show']);
            Route::post('/send-message', [WhatsAppController::class, 'sendMessage']);
            Route::post('/send-media', [WhatsAppController::class, 'sendMediaMessage']);
            Route::post('/send-template', [WhatsAppController::class, 'sendTemplate']);
            Route::post('/mark-as-read', [WhatsAppController::class, 'markAsRead']);
            Route::get('/templates', [WhatsAppController::class, 'getTemplates']);
            Route::post('/templates/sync', [WhatsAppController::class, 'syncTemplates']);
            Route::get('/contacts', [WhatsAppController::class, 'getContacts']);
            Route::put('/contacts/{id}/opt-in', [WhatsAppController::class, 'updateContactOptIn']);
            Route::put('/conversations/{id}/status', [WhatsAppController::class, 'updateConversationStatus']);
            Route::put('/conversations/{id}/assign', [WhatsAppController::class, 'assignConversation']);
            Route::post('/react', [WhatsAppController::class, 'react']);
            Route::get('/media/{mediaId}', [WhatsAppController::class, 'getMedia']);
        });

        // Staff directory (matches staff to WhatsApp conversations by phone)
        Route::get('/staff-list', [StaffChatController::class, 'getStaffList']);
    });
});
