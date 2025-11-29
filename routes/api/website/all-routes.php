<?php

use App\Http\Controllers\Auth\Website\AuthenticatedSessionController;
use App\Http\Controllers\Authenticated\CRM\AuthorizedBrandsController;
use App\Http\Controllers\Authenticated\CRM\CategoriesController;
use App\Http\Controllers\Authenticated\CRM\ServicesController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'website'], function () {
    Route::get('/categories', [CategoriesController::class, 'webIndex']);
    Route::get('/categories/{slug}', [CategoriesController::class, 'webShow']);
    Route::get('/services', [ServicesController::class, 'webIndex']);
    Route::get('/services/{slug}', [ServicesController::class, 'webShow']);
    Route::get('/authorized-brands', [AuthorizedBrandsController::class, 'getBrandsMeta']);
    Route::post('/auth/check-credentials', [AuthenticatedSessionController::class, 'otp']);
    Route::post('/auth/sign-in', [AuthenticatedSessionController::class, 'signin']);
    Route::post('/auth/verify-otp', [AuthenticatedSessionController::class, 'veriyotp']);

});

