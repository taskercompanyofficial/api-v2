<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Authenticated\CRM\JobSheetController;
use App\Http\Controllers\FilesController;
use Illuminate\Http\Request;
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



include_once __DIR__ . '/api/crm/all-routes.php';
include_once __DIR__ . '/api/website/all-routes.php';
include_once __DIR__ . '/api/staff-app/all-routes.php';
include_once __DIR__ . '/api/tasker-app/all-routes.php';
include_once __DIR__ . '/api/webhook.php';
