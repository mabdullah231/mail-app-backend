<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\NotificationRuleController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DomainVerificationController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\TestEmailController;

/**
 * Public Authentication Routes
 */
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-email', [AuthController::class, 'resendEmail']);
    Route::post('/resend-email-with-code', [AuthController::class, 'resendEmailWithCode']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-forgot-password', [AuthController::class, 'verifyForgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

/**
 * Protected Routes (require auth)
 */
Route::middleware(['auth:sanctum'])->group(function () {
    // Upload Routes
    Route::post('/upload/image', [UploadController::class, 'uploadImage']);
    // Company Routes
    Route::prefix('company')->group(function () {
        Route::get('/details', [CompanyController::class, 'getDetails']);
        Route::post('/store-or-update', [CompanyController::class, 'storeOrUpdate']);
        Route::get('/storage-info', [CompanyController::class, 'getStorageInfo']);
    });

    // Company Dashboard Stats
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Customer Management Routes
    Route::apiResource('customers', CustomerController::class);
    
    // Template Management Routes
    Route::apiResource('templates', TemplateController::class);
    
    // Notification Rules Routes - ADD THIS SECTION
    Route::apiResource('notification-rules', NotificationRuleController::class);
    
    // Reminder Routes
    Route::apiResource('reminders', ReminderController::class);
    Route::patch('/reminders/{reminder}/toggle-status', [ReminderController::class, 'toggleStatus']);
    Route::get('/reminders/customer/{customerId}', [ReminderController::class, 'getByCustomer']);
    Route::get('/reminders/template/{templateId}', [ReminderController::class, 'getByTemplate']);

    // Email Sending Routes
    Route::prefix('email')->middleware('company.rate.limit:10,1')->group(function () {
        Route::post('/send-single', [EmailController::class, 'sendSingle']);
        Route::post('/send-bulk', [EmailController::class, 'sendBulk']);
        Route::post('/send-to-all', [EmailController::class, 'sendToAll']);
        Route::get('/stats', [EmailController::class, 'getStats']);
        Route::get('/logs', [EmailController::class, 'getLogs']);
        // CSV export for company email logs
        Route::get('/export', [EmailController::class, 'exportLogs']);
    });

    // SMS Sending Routes
    Route::prefix('sms')->middleware('company.rate.limit:20,1')->group(function () {
        Route::post('/send-single', [SmsController::class, 'sendSingle']);
        Route::post('/send-bulk', [SmsController::class, 'sendBulk']);
        Route::get('/stats', [SmsController::class, 'getStats']);
        Route::get('/logs', [SmsController::class, 'getLogs']);
        // CSV export for company sms logs
        Route::get('/export', [SmsController::class, 'exportLogs']);
    });

    // Automation Routes
    Route::prefix('automation')->middleware(['company.rate.limit:5,1'])->group(function () {
        Route::post('/create-reminder', [AutomationController::class, 'createAutomatedReminder']);
        Route::post('/process-due-reminders', [AutomationController::class, 'processDueReminders']);
    });

    // Payments Routes (Stripe)
    Route::prefix('payments')->group(function () {
        Route::post('/stripe/create-intent', [PaymentController::class, 'createStripePayment']);
    });

    // Subscription Routes
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'getCurrent']);
        Route::get('/pricing', [SubscriptionController::class, 'getPricing']);
        Route::post('/subscribe-branding-removal', [SubscriptionController::class, 'subscribeBrandingRemoval']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
    });

    // Domain Verification Routes (if needed)
});

// Stripe Webhook (public)
Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook']);

// Test Email Route (outside auth middleware for testing)
Route::get('/test-email', [TestEmailController::class, 'sendTestEmail']);
Route::get('/test-email-config', function () {
    return response()->json([
        'mailer' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'port' => config('mail.mailers.smtp.port'),
        'from' => config('mail.from.address'),
        'from_name' => config('mail.from.name'),
    ]);
});
Route::get('/test-stripe-config', function () {
    return response()->json([
        'publishable' => config('services.stripe.key'),
        'secret_present' => !empty(config('services.stripe.secret')),
    ]);
});