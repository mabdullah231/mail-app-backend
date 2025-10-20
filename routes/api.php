<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\NotificationRuleController; // Add this line
use App\Http\Controllers\EmailController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DomainVerificationController;

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

// Webhook Routes (Public - no authentication required)
Route::prefix('webhooks')->group(function () {
    Route::post('/paypal', [PaymentController::class, 'handlePayPalWebhook']);
    Route::post('/stripe', [PaymentController::class, 'handleStripeWebhook']);
    Route::post('/donation', [PaymentController::class, 'handleDonationWebhook']);
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
    Route::post('/verify-2fa', [TwoFactorController::class, 'verify']);
});

// Protected Routes (Require Authentication only)
Route::middleware('auth:sanctum')->group(function () {
    // Two Factor Authentication Routes (excluded from 2FA middleware)
    Route::prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/generate-secret', [TwoFactorController::class, 'generateSecret']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
    });
});

// Protected Routes (Require Authentication + 2FA)
Route::middleware(['auth:sanctum', '2fa'])->group(function () {
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
        Route::get('/storage-info', [CompanyController::class, 'getStorageInfo']);
    });

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
    });

    // SMS Sending Routes
    Route::prefix('sms')->middleware('company.rate.limit:20,1')->group(function () {
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

    // Payment Routes
    Route::prefix('payment')->group(function () {
        Route::post('/paypal/create', [PaymentController::class, 'createPayPalPayment']);
        Route::post('/stripe/create', [PaymentController::class, 'createStripePayment']);
        Route::post('/donation/create', [PaymentController::class, 'createDonationPayment']);
    });

    // Domain Verification Routes
    Route::prefix('domain')->group(function () {
        Route::get('/verification-status', [DomainVerificationController::class, 'getVerificationStatus']);
        Route::get('/dns-templates', [DomainVerificationController::class, 'getDNSTemplates']);
        Route::post('/test-deliverability', [DomainVerificationController::class, 'testDeliverability']);
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