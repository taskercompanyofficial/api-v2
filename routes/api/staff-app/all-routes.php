<?php

use App\Http\Controllers\Auth\App\AuthenticatedSessionController;
use App\Http\Controllers\Authenticated\StaffApp\AttendanceController;
use App\Http\Controllers\Authenticated\StaffApp\LeaveController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'staff-app'], function () {
    Route::post('/auth/check-credentials', [AuthenticatedSessionController::class, 'otp']);
    Route::post('/auth/sign-in', [AuthenticatedSessionController::class, 'signin']);
    Route::post('/auth/verify-otp', [AuthenticatedSessionController::class, 'veriyotp']);
    
    Route::group(['middleware' => ['auth:sanctum']], function () {
        // Auth routes
        Route::post('/auth/sign-out', [AuthenticatedSessionController::class, 'signOut']);
        Route::get('/auth/me', [AuthenticatedSessionController::class, 'me']);
        
        // Attendance routes
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance/history', [AttendanceController::class, 'history']);
        Route::get('/attendance/stats', [AttendanceController::class, 'stats']);
        Route::get('/attendance/export', [AttendanceController::class, 'exportReport']);
        
        // Leave routes
        Route::get('/leave-types', [LeaveController::class, 'types']);
        Route::get('/leave-balance', [LeaveController::class, 'balance']);
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/{id}', [LeaveController::class, 'show']);
        Route::post('/leaves/apply', [LeaveController::class, 'apply']);
        Route::put('/leaves/{id}/cancel', [LeaveController::class, 'cancel']);
        Route::put('/leaves/{id}/approve', [LeaveController::class, 'approve']);
        Route::put('/leaves/{id}/reject', [LeaveController::class, 'reject']);
    });
});
