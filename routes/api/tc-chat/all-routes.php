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
            Route::post('/conversations/{id}/mark-as-read', [WhatsAppController::class, 'markConversationAsRead']);
            Route::get('/templates', [WhatsAppController::class, 'getTemplates']);
            Route::post('/templates/sync', [WhatsAppController::class, 'syncTemplates']);
            Route::get('/contacts', [WhatsAppController::class, 'getContacts']);
            Route::put('/contacts/{id}/opt-in', [WhatsAppController::class, 'updateContactOptIn']);
            Route::put('/conversations/{id}/status', [WhatsAppController::class, 'updateConversationStatus']);
            Route::put('/conversations/{id}/assign', [WhatsAppController::class, 'assignConversation']);
            Route::post('/react', [WhatsAppController::class, 'react']);
            Route::put('/conversations/{id}/pin', [WhatsAppController::class, 'togglePin']);
            Route::get('/media/{mediaId}', [WhatsAppController::class, 'getMedia']);
        });

        // Staff directory (matches staff to WhatsApp conversations by phone)
        Route::get('/staff-list', [StaffChatController::class, 'getStaffList']);

        // Staff Tasks (admin-assigned, read-only for staff, status update only)
        Route::get('/tasks', [\App\Http\Controllers\Authenticated\StaffApp\StaffTaskController::class, 'index']);
        Route::get('/tasks/summary', [\App\Http\Controllers\Authenticated\StaffApp\StaffTaskController::class, 'summary']);
        Route::patch('/tasks/{id}/status', [\App\Http\Controllers\Authenticated\StaffApp\StaffTaskController::class, 'updateStatus']);

        // Staff Todos (personal, full CRUD)
        Route::get('/todos', [\App\Http\Controllers\Authenticated\StaffApp\StaffTodoController::class, 'index']);
        Route::post('/todos', [\App\Http\Controllers\Authenticated\StaffApp\StaffTodoController::class, 'store']);
        Route::put('/todos/{id}', [\App\Http\Controllers\Authenticated\StaffApp\StaffTodoController::class, 'update']);
        Route::delete('/todos/{id}', [\App\Http\Controllers\Authenticated\StaffApp\StaffTodoController::class, 'destroy']);
    });
});
