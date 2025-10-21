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

Route::middleware(['auth:sanctum'])->group(function () {
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