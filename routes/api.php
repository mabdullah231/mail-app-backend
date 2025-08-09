<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/resend-email', [AuthController::class, 'resendEmail']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-forgot-password', [AuthController::class, 'verifyForgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected Routes (Require Authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Company Details Routes
    Route::prefix('company')->group(function () {
        Route::post('/store-or-update', [CompanyController::class, 'storeOrUpdate']);
        Route::get('/details', [CompanyController::class, 'getDetails']);
    });
});