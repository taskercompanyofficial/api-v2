<?php

use App\Http\Controllers\Auth\Vendor\AuthenticatedSessionController;
use App\Http\Controllers\Auth\Vendor\OtpController;
use App\Http\Controllers\Auth\VendorStaff\AuthController as VendorStaffAuthController;
use App\Http\Controllers\Authenticated\Vendor\WorkOrderController;
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

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::get('/work-orders/summary', [WorkOrderController::class, 'summary']);
        Route::get('/work-orders/file-types', [WorkOrderController::class, 'getFileTypes']);
        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);
        Route::post('/work-orders/{id}/accept', [WorkOrderController::class, 'accept']);
        Route::post('/work-orders/{id}/reject', [WorkOrderController::class, 'reject']);
        Route::post('/work-orders/{id}/update-details', [WorkOrderController::class, 'updateDetails']);
        Route::post('/work-orders/{id}/update-status', [WorkOrderController::class, 'updateStatus']);
        Route::post('/work-orders/{id}/update-status-by-slug', [WorkOrderController::class, 'updateStatusBySlug']);
        Route::post('/work-orders/{id}/schedule', [WorkOrderController::class, 'schedule']);
        Route::post('/work-orders/{id}/cancel', [WorkOrderController::class, 'cancel']);
        Route::post('/work-orders/{id}/assign-staff', [WorkOrderController::class, 'assignStaff']);
        Route::post('/work-orders/{id}/upload-file', [WorkOrderController::class, 'uploadFile']);
        Route::delete('/work-orders/{id}/files/{fileId}', [WorkOrderController::class, 'deleteFile']);

        // Staff Management
        Route::get('/staff', [\App\Http\Controllers\Authenticated\Vendor\StaffController::class, 'index']);
        Route::post('/staff', [\App\Http\Controllers\Authenticated\Vendor\StaffController::class, 'store']);

        // Lookups
        Route::get('/work-orders/service-concerns', [WorkOrderController::class, 'getServiceConcerns']);
        Route::get('/work-orders/service-concerns/{id}/sub-concerns', [WorkOrderController::class, 'getServiceSubConcerns']);
        Route::get('/work-orders/warranty-types', [WorkOrderController::class, 'getWarrantyTypes']);
    });
});

// Vendor Staff Auth Routes (separate guard)
Route::group(['prefix' => 'vendor-staff'], function () {
    Route::post('/login', [VendorStaffAuthController::class, 'login']);

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::get('/me', [VendorStaffAuthController::class, 'me']);
        Route::post('/sign-out', [VendorStaffAuthController::class, 'signOut']);
        Route::post('/change-password', [VendorStaffAuthController::class, 'changePassword']);

        // Staff can view assigned work orders (read-only)
        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);
        Route::get('/work-orders/summary', [WorkOrderController::class, 'summary']);
    });
});
