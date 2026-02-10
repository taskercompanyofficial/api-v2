<?php

use App\Http\Controllers\Auth\TaskerApp\AuthenticatedSessionController;
use App\Http\Controllers\Authenticated\CRM\AuthorizedBrandsController;
use App\Http\Controllers\Authenticated\CRM\CategoriesController;
use App\Http\Controllers\Authenticated\CRM\CustomerAddressController;
use App\Http\Controllers\Authenticated\CRM\MajorClientsController;
use App\Http\Controllers\Authenticated\CRM\ParentServicesController;
use App\Http\Controllers\Authenticated\CRM\ServicesController;
use App\Http\Controllers\TaskerApp\NotificationController;
use App\Http\Controllers\TaskerApp\CommercialQuoteController;
use App\Http\Controllers\TaskerApp\WorkOrderController as TaskerWorkOrderController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'tasker-app'], function () {
    Route::get('/categories', [CategoriesController::class, 'webIndex']);
    Route::get('/categories/{slug}', [CategoriesController::class, 'webShow']);
    Route::get('/services', [ServicesController::class, 'webIndex']);
    Route::get('/services/{slug}', [ServicesController::class, 'webShow']);
    Route::get('/authorized-brands', [AuthorizedBrandsController::class, 'getBrandsMeta']);
    Route::get('/major-clients', [MajorClientsController::class, 'getBrandsMeta']);
    Route::post('/auth/check-credentials', [AuthenticatedSessionController::class, 'otp']);
    Route::post('/auth/sign-in', [AuthenticatedSessionController::class, 'signin']);
    Route::post('/auth/verify-otp', [AuthenticatedSessionController::class, 'verifyotp']);
    Route::apiResource('/address', CustomerAddressController::class);    
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::get('/auth/me', [AuthenticatedSessionController::class,'me']);
        Route::post('/auth/update', [AuthenticatedSessionController::class,'update']);
        Route::get('/parent-services', [ParentServicesController::class,'parentServices']);
        Route::get('/trending-services', [TaskerWorkOrderController::class, 'trendingParentServices']);
        Route::get('/parent-services/{slug}', [ParentServicesController::class,'show']);
        
        // Work Order routes (customer)
        Route::prefix('work-orders')->group(function () {
            Route::post('/', [TaskerWorkOrderController::class, 'store']);
            Route::get('/my-work-orders', [TaskerWorkOrderController::class, 'customerWorkOrders']);
            Route::get('/{workOrderNumber}', [TaskerWorkOrderController::class, 'show']);
            Route::post('/{workOrderNumber}/cancel', [TaskerWorkOrderController::class, 'cancel']);
        });
        
        // Notification routes
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        });

        // Commercial Quote routes
        Route::prefix('commercial-quotes')->group(function () {
            Route::post('/', [CommercialQuoteController::class, 'store']);
            Route::get('/', [CommercialQuoteController::class, 'index']);
            Route::get('/{id}', [CommercialQuoteController::class, 'show']);
        });

        
    });

});
