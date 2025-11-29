<?php

use App\Http\Controllers\Auth\App\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'staff-app'], function () {
    Route::post('/auth/check-credentials', [AuthenticatedSessionController::class, 'otp']);
    Route::post('/auth/sign-in', [AuthenticatedSessionController::class, 'signin']);
    Route::post('/auth/verify-otp', [AuthenticatedSessionController::class, 'veriyotp']);
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::post('/auth/sign-out', [AuthenticatedSessionController::class, 'signOut']);
        Route::get('/auth/me', [AuthenticatedSessionController::class, 'me']);
    });
});
