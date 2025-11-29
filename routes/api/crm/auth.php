<?php

use App\Http\Controllers\Auth\CRM\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
Route::group(['prefix' => 'crm/auth'], function () {
    Route::group(['middleware' => ['guest']], function () {
        Route::post('/check-credentials', [AuthenticatedSessionController::class, 'checkCredentials']);
        Route::post('/sign-in', [AuthenticatedSessionController::class, 'store']);
    });
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::post('/sign-out', [AuthenticatedSessionController::class, 'signOut']);
        Route::get('/me', [AuthenticatedSessionController::class, 'me']);
    });
});