<?php

use App\Http\Controllers\Auth\Vendor\AuthenticatedSessionController;
use App\Http\Controllers\Auth\Vendor\OtpController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'vendor'], function () {
    Route::post('/register', [AuthenticatedSessionController::class, 'register']);
    Route::post('/login', [AuthenticatedSessionController::class, 'login']);
    Route::get('/me', [AuthenticatedSessionController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/sign-out', [AuthenticatedSessionController::class, 'signOut'])->middleware('auth:sanctum');
    Route::post('/update-profile', [AuthenticatedSessionController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthenticatedSessionController::class, 'changePassword'])->middleware('auth:sanctum');

    // OTP-based auth
    Route::prefix('auth')->group(function () {
        Route::post('/check-credentials', [OtpController::class, 'otp']);
        Route::post('/verify-otp', [OtpController::class, 'verifyotp']);
    });
});
