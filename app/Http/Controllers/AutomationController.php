<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Models\Customer;
use App\Models\Template;
use App\Models\Reminder;
use App\Models\EmailLog;
use App\Models\SmsLog;
use App\Mail\CustomEmail;
use Twilio\Rest\Client;
use Carbon\Carbon;

class AutomationController extends Controller
{
    /**
     * Process all due reminders (called by scheduler)
     */
    public function processDueReminders()
    {
        $dueReminders = Reminder::with(['customer', 'template', 'customer.company'])
            ->where('active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        $processed = 0;
        $errors = 0;

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                $this->updateNextRunTime($reminder);
                $processed++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Reminder processing failed: ' . $e->getMessage(), [
                    'reminder_id' => $reminder->id,
                    'customer_id' => $reminder->customer_id
                ]);
                $errors++;
            }
        }

        return response()->json([
            'message' => "Processed $processed reminders, $errors errors",
            'processed' => $processed,
            'errors' => $errors
        ]);
    }

    /**
     * Send individual reminder
     */
    private function sendReminder(Reminder $reminder)
    {
        $customer = $reminder->customer;
        $template = $reminder->template;
        $company = $customer->company;
        
        // Get current date for comparison with reminder date
        $today = now()->startOfDay();
        $reminderDate = Carbon::parse($reminder->start_at)->startOfDay();
        $daysDifference = $today->diffInDays($reminderDate, false);
        
        // Check if we should send notification based on notification rules
        $shouldSendNotification = false;
        
        // Get notification rules from recurrence_rule
        $notificationRules = $reminder->recurrence_rule['notification_rules'] ?? [];
        
        // If no specific rules, use default behavior
        if (empty($notificationRules)) {
            $shouldSendNotification = true;
        } else {
            // Check each notification rule
            foreach ($notificationRules as $rule) {
                if ($rule === 'on date' && $daysDifference === 0) {
                    $shouldSendNotification = true;
                    break;
                } else if (preg_match('/(\d+)\s+(day|days|week|weeks|month|months)\s+(before|after)/', $rule, $matches)) {
                    $value = (int)$matches[1];
                    $unit = $matches[2];
                    $type = $matches[3];
                    
                    // Convert to days
                    if (strpos($unit, 'week') === 0) {
                        $value *= 7;
                    } else if (strpos($unit, 'month') === 0) {
                        $value *= 30;
                    }
                    
                    // Check if today matches the rule
                    if ($type === 'before' && $daysDifference === $value) {
                        $shouldSendNotification = true;
                        break;
                    } else if ($type === 'after' && $daysDifference === -$value) {
                        $shouldSendNotification = true;
                        break;
                    }
                }
            }
        }
        
        // Only send notifications if rules match
        if ($shouldSendNotification) {
            // Check if customer wants email notifications
            if (in_array($customer->notification, ['email', 'both'])) {
                $this->sendReminderEmail($customer, $template, $company);
            }

            // Check if customer wants SMS notifications
            if (in_array($customer->notification, ['sms', 'both']) && $customer->sms_opt_in && $customer->phone) {
                $this->sendReminderSms($customer, $template, $company);
            }
        }
    }

    /**
     * Send reminder email
     */
    private function sendReminderEmail($customer, $template, $company)
    {
        // Check email limits
        if (!$this->checkEmailLimits($company)) {
            return;
        }

        $emailContent = $this->processEmailTemplate($template, $customer, $company);
        $subject = "Reminder: " . $template->title;

        try {
            Mail::to($customer->email)
                ->send(new CustomEmail($emailContent, $subject, $company));

            EmailLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $subject,
                'status' => 'sent',
                'sent_at' => now(),
                'is_automated' => true
            ]);

        } catch (\Exception $e) {
            EmailLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $subject,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'is_automated' => true
            ]);
        }
    }

    /**
     * Send reminder SMS
     */
    private function sendReminderSms($customer, $template, $company)
    {
        // Check SMS limits
        if (!$this->checkSmsLimits($company)) {
            return;
        }

        $smsContent = $this->processSmsTemplate($template, $customer, $company);

        try {
            $twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $message = $twilio->messages->create(
                $customer->phone,
                [
                    'from' => config('services.twilio.phone'),
                    'body' => $smsContent
                ]
            );

            SmsLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'message' => $smsContent,
                'status' => 'sent',
                'sent_at' => now(),
                'provider_id' => $message->sid,
                'is_automated' => true
            ]);

        } catch (\Exception $e) {
            SmsLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'message' => $smsContent,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'is_automated' => true
            ]);
        }
    }

    /**
     * Update next run time based on recurrence rule
     */
    private function updateNextRunTime(Reminder $reminder)
    {
        $recurrenceRule = $reminder->recurrence_rule;
        $nextRun = null;

        if ($recurrenceRule && isset($recurrenceRule['frequency'])) {
            $frequency = $recurrenceRule['frequency'];
            
            switch ($frequency) {
                case 'Daily':
                    $nextRun = now()->addDay();
                    break;
                case '3 days':
                    $nextRun = now()->addDays(3);
                    break;
                case 'Weekly':
                    $nextRun = now()->addWeek();
                    break;
                case '2 weeks':
                    $nextRun = now()->addWeeks(2);
                    break;
            }
        }

        // Check if there's an expiration date and stop after that
        if (isset($recurrenceRule['expires_at'])) {
            $expiresAt = Carbon::parse($recurrenceRule['expires_at']);
            if ($nextRun && $nextRun->isAfter($expiresAt)) {
                $reminder->update(['active' => false]);
                return;
            }
        }

        if ($nextRun) {
            $reminder->update(['next_run_at' => $nextRun]);
        } else {
            // One-time reminder, deactivate
            $reminder->update(['active' => false]);
        }
    }

    /**
     * Create automated reminder for customer
     */
    public function createAutomatedReminder(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'start_date' => 'required|date',
            'frequency' => 'required|in:Daily,3 days,Weekly,2 weeks,one-time',
            'expires_at' => 'nullable|date|after:start_date',
            'notification_rules' => 'nullable|array' // e.g., ["1 week before", "2 days before", "on date", "2 days after"]
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $customer = Customer::where('id', $request->customer_id)
                          ->where('company_id', $company->id)
                          ->firstOrFail();

        $template = Template::where('id', $request->template_id)
                          ->where('company_id', $company->id)
                          ->firstOrFail();

        $recurrenceRule = [
            'frequency' => $request->frequency,
            'expires_at' => $request->expires_at
        ];

        if ($request->notification_rules) {
            $recurrenceRule['notification_rules'] = $request->notification_rules;
        }

        $reminder = Reminder::create([
            'customer_id' => $customer->id,
            'template_id' => $template->id,
            'start_at' => $request->start_date,
            'recurrence_rule' => $recurrenceRule,
            'next_run_at' => $request->start_date,
            'active' => true
        ]);

        return response()->json([
            'message' => 'Automated reminder created successfully',
            'reminder' => $reminder
        ]);
    }

    /**
     * Process email template with placeholders
     */
    private function processEmailTemplate($template, $customer, $company)
    {
        $content = $template->body_html;

        // Replace customer placeholders
        $content = str_replace('{{customer.name}}', $customer->name, $content);
        $content = str_replace('{{customer.email}}', $customer->email, $content);
        $content = str_replace('{{customer.phone}}', $customer->phone ?? '', $content);
        $content = str_replace('{{customer.address}}', $customer->address ?? '', $content);
        $content = str_replace('{{customer.country}}', $customer->country ?? '', $content);

        // Replace company placeholders
        $content = str_replace('{{company.name}}', $company->name, $content);
        $content = str_replace('{{company.address}}', $company->address, $content);

        // Add company logo if exists
        if ($company->logo) {
            $logoUrl = url($company->logo);
            $content = str_replace('{{company.logo}}', "<img src='$logoUrl' alt='Company Logo' style='max-width: 200px;'>", $content);
        }

        // Add company signature if exists
        if ($company->signature) {
            $signatureUrl = url($company->signature);
            $content = str_replace('{{company.signature}}', "<img src='$signatureUrl' alt='Signature' style='max-width: 300px;'>", $content);
        }

        // Add branding if not removed
        $subscription = $company->subscription;
        if (!$subscription || !$subscription->canRemoveBranding()) {
            $content .= '<br><br><p style="font-size: 12px; color: #666; text-align: center;">Powered by <a href="https://emailzus.com" style="color: #007bff;">Email Zus</a></p>';
        }

        return $content;
    }

    /**
     * Process SMS template with placeholders
     */
    private function processSmsTemplate($template, $customer, $company)
    {
        $content = $template->body_html; // For SMS, this will be plain text

        // Replace customer placeholders
        $content = str_replace('{{customer.name}}', $customer->name, $content);
        $content = str_replace('{{company.name}}', $company->name, $content);

        // Add branding if not removed (shorter for SMS)
        $subscription = $company->subscription;
        if (!$subscription || !$subscription->canRemoveBranding()) {
            $content .= ' - Powered by Email Zus';
        }

        return $content;
    }

    /**
     * Check email sending limits
     */
    private function checkEmailLimits($company)
    {
        $subscription = $company->subscription;
        $monthlyLimit = $subscription ? $subscription->getEmailLimit() : 100;

        $currentMonthSent = EmailLog::whereHas('customer', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->whereMonth('created_at', now()->month)
        ->where('status', 'sent')
        ->count();

        return $currentMonthSent < $monthlyLimit;
    }

    /**
     * Check SMS sending limits
     */
    private function checkSmsLimits($company)
    {
        $subscription = $company->subscription;
        $monthlyLimit = $subscription ? $subscription->getSmsLimit() : 10;

        $currentMonthSent = SmsLog::whereHas('customer', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->whereMonth('created_at', now()->month)
        ->where('status', 'sent')
        ->count();

        return $currentMonthSent < $monthlyLimit;
    }
}
