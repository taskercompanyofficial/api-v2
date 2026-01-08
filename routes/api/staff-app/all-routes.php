<?php

use App\Http\Controllers\Auth\App\AuthenticatedSessionController;
use App\Http\Controllers\Authenticated\CRM\ParentServicesController;
use App\Http\Controllers\Authenticated\CRM\ServiceConcernController;
use App\Http\Controllers\Authenticated\CRM\ServiceSubConcernController;
use App\Http\Controllers\Authenticated\CRM\WorkOrderFileController;
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
        Route::get('/attendance/{id}', [AttendanceController::class, 'show']);

        // Leave routes
        Route::get('/leave-types', [LeaveController::class, 'types']);
        Route::get('/leave-balance', [LeaveController::class, 'balance']);
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/{id}', [LeaveController::class, 'show']);
        Route::post('/leaves/apply', [LeaveController::class, 'apply']);
        Route::put('/leaves/{id}/cancel', [LeaveController::class, 'cancel']);
        Route::put('/leaves/{id}/approve', [LeaveController::class, 'approve']);
        Route::put('/leaves/{id}/reject', [LeaveController::class, 'reject']);

        // Notification routes
        Route::get('/notifications', [App\Http\Controllers\Authenticated\StaffApp\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [App\Http\Controllers\Authenticated\StaffApp\NotificationController::class, 'unreadCount']);
        Route::put('/notifications/{id}/read', [App\Http\Controllers\Authenticated\StaffApp\NotificationController::class, 'markAsRead']);
        Route::put('/notifications/mark-all-read', [App\Http\Controllers\Authenticated\StaffApp\NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/register-token', [App\Http\Controllers\Authenticated\StaffApp\NotificationController::class, 'registerToken']);

        // Profile routes
        Route::get('/profile/status', [App\Http\Controllers\Authenticated\StaffApp\StaffProfileController::class, 'getProfileStatus']);
        Route::put('/profile/complete', [App\Http\Controllers\Authenticated\StaffApp\StaffProfileController::class, 'completeProfile']);

        // File Types route
        Route::get('/file-types', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'getFileTypes']);

        // Work Order routes
        Route::get('/work-orders/summary', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'summary']);
        Route::get('/work-orders', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'index']);
        Route::get('/work-orders/{id}', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'show']);
        Route::post('/work-orders/{id}/accept', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'accept']);
        Route::post('/work-orders/{id}/reject', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'reject']);
        Route::patch('/work-orders/{id}/update-details', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'updateDetails']);
        Route::post('/work-orders/{id}/upload-file', [WorkOrderFileController::class, 'store']);
        Route::get('/work-orders/{id}/files/{fileId}/download', [WorkOrderFileController::class, 'download']);
        Route::get('/work-orders/{id}/files/download-all', [WorkOrderFileController::class, 'downloadAll']);
        Route::delete('/work-orders/{id}/files/{fileId}', [WorkOrderFileController::class, 'destroy']);
        Route::post('/work-orders/{id}/update-status', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'updateStatus']);
        Route::post('/work-orders/{id}/update-status-by-slug', [App\Http\Controllers\Authenticated\StaffApp\WorkOrderController::class, 'updateStatusBySlug']);
        Route::get('/parent-services-raw', [ParentServicesController::class, 'parentServicesRaw']);
        Route::get('/service-concerns-raw', [ServiceConcernController::class, 'serviceConcernsRaw']);
        Route::get('/service-sub-concerns-raw', [ServiceSubConcernController::class, 'serviceSubConcernsRaw']);
        Route::get('/warranty-types-raw', [\App\Http\Controllers\Authenticated\CRM\WarrantyTypeController::class, 'warrantyTypesRaw']);
    });
});
