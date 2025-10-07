<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\DashboardController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/test-email', function () {
    Mail::raw('This is a test email from Laravel!', function ($message) {
        $message->to('xakikak457@inilas.com')
                ->subject('Test Email from Laravel')
                ->from(
                    env('MAIL_FROM_ADDRESS'), // info@macodes.dev
                    env('MAIL_FROM_NAME')     // Email ZUS
                );
    });

    return response()->json(['message' => 'Test email sent']);
});

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
    // Profile Routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        Route::get('/profile', [AuthController::class, 'getProfile']);
    });

    // Dashboard Routes
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Company Details Routes
    Route::prefix('company')->group(function () {
        Route::post('/store-or-update', [CompanyController::class, 'storeOrUpdate']);
        Route::get('/details', [CompanyController::class, 'getDetails']);
    });

    // Customer Management Routes
    Route::apiResource('customers', CustomerController::class);
    
    // Template Management Routes
    Route::apiResource('templates', TemplateController::class);
    
    // Reminder Routes
    Route::apiResource('reminders', ReminderController::class);
    Route::patch('/reminders/{reminder}/toggle-status', [ReminderController::class, 'toggleStatus']);
    Route::get('/reminders/customer/{customerId}', [ReminderController::class, 'getByCustomer']);
    Route::get('/reminders/template/{templateId}', [ReminderController::class, 'getByTemplate']);

    // Email Sending Routes
    Route::prefix('email')->group(function () {
        Route::post('/send-single', [EmailController::class, 'sendSingle']);
        Route::post('/send-bulk', [EmailController::class, 'sendBulk']);
        Route::post('/send-to-all', [EmailController::class, 'sendToAll']);
        Route::get('/stats', [EmailController::class, 'getStats']);
    });

    // SMS Sending Routes
    Route::prefix('sms')->group(function () {
        Route::post('/send-single', [SmsController::class, 'sendSingle']);
        Route::post('/send-bulk', [SmsController::class, 'sendBulk']);
        Route::get('/stats', [SmsController::class, 'getStats']);
    });

    // Subscription Management Routes
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'getCurrent']);
        Route::post('/subscribe-branding-removal', [SubscriptionController::class, 'subscribeBrandingRemoval']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/pricing', [SubscriptionController::class, 'getPricing']);
        Route::get('/check-expiration', [SubscriptionController::class, 'checkExpiration']);
    });

    // Automation Routes
    Route::prefix('automation')->group(function () {
        Route::post('/create-reminder', [AutomationController::class, 'createAutomatedReminder']);
        Route::post('/process-due-reminders', [AutomationController::class, 'processDueReminders']);
    });

    // Super Admin Routes (User Type 0 only)
    Route::middleware(['superadmin'])->prefix('super-admin')->group(function () {
        Route::get('/dashboard-stats', [SuperAdminController::class, 'getDashboardStats']);
        Route::get('/users', [SuperAdminController::class, 'getAllUsers']);
        Route::put('/users/{userId}/toggle-status', [SuperAdminController::class, 'toggleUserStatus']);
        Route::delete('/users/{userId}', [SuperAdminController::class, 'deleteUser']);
        Route::put('/users/{userId}/limit-account', [SuperAdminController::class, 'limitBusinessAccount']);
        Route::get('/emails', [SuperAdminController::class, 'getAllEmails']);
        Route::get('/emails/export', [SuperAdminController::class, 'exportEmails']);
        Route::get('/templates', [SuperAdminController::class, 'getAllTemplates']);
        Route::put('/companies/{companyId}/limits', [SuperAdminController::class, 'setCompanyLimits']);
        Route::get('/email-logs', [SuperAdminController::class, 'getEmailLogs']);
    });
});