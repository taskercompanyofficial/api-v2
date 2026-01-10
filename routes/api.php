<?php

use App\Events\TestEvent;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Authenticated\CRM\JobSheetController;
use App\Http\Controllers\FilesController;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['middleware' => ['guest']], function () {
    Route::apiResource('crm/appearance', AppearanceController::class);
    Route::apiResource('/files', FilesController::class);
    Route::get('/work-orders/{id}/job-sheet', [JobSheetController::class, 'generate']);
    Route::get('/work-orders/{id}/job-sheet/preview', [JobSheetController::class, 'preview']);
    Route::get('/test-pdf', [JobSheetController::class, 'test']);
});

Route::post('/test/notification', function () {
    $notification = Notification::create([
        'user_id' => 1,
        'title' => 'Test Notification',
        'body' => 'This is a test notification',
        'user_type' => 'customer',
        'message' => 'This is a test notification',
    ]);
    TestEvent::dispatch($notification);
    return response()->json([
        'message' => 'Notification sent successfully',
    ]);
});

Broadcast::routes(["middleware" => "auth:sanctum"]);

include_once __DIR__ . '/api/crm/all-routes.php';
include_once __DIR__ . '/api/website/all-routes.php';
include_once __DIR__ . '/api/staff-app/all-routes.php';
include_once __DIR__ . '/api/tasker-app/all-routes.php';
include_once __DIR__ . '/api/webhook.php';
