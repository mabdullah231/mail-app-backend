<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Template;
use App\Models\SmsLog;
use App\Models\CompanyDetail;
use Twilio\Rest\Client;

class SmsController extends Controller
{
    private $twilio;

    public function __construct()
    {
        // Initialize Twilio client (you'll need to add Twilio credentials to .env)
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
    }

    /**
     * Send SMS to single customer
     */
    public function sendSingle(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'message' => 'required|string|max:1600'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Check SMS limits
        if (!$this->checkSmsLimits($company)) {
            return response()->json(['message' => 'SMS limit exceeded'], 429);
        }

        $customer = Customer::findOrFail($request->customer_id);
        $template = Template::findOrFail($request->template_id);

        // Verify ownership and SMS opt-in
        if ($customer->company_id !== $company->id || $template->company_id !== $company->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (!$customer->sms_opt_in || !$customer->phone) {
            return response()->json(['message' => 'Customer has not opted in for SMS or no phone number'], 400);
        }

        $smsContent = $this->processTemplate($template, $customer, $company);
        
        try {
            $message = $this->twilio->messages->create(
                $customer->phone,
                [
                    'from' => config('services.twilio.phone'),
                    'body' => $smsContent
                ]
            );

            // Log the SMS
            SmsLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'message' => $smsContent,
                'status' => 'sent',
                'sent_at' => now(),
                'provider_id' => $message->sid
            ]);

            return response()->json(['message' => 'SMS sent successfully']);

        } catch (\Exception $e) {
            SmsLog::create([
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'message' => $smsContent,
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            return response()->json(['message' => 'Failed to send SMS'], 500);
        }
    }

    /**
     * Send SMS to multiple customers
     */
    public function sendBulk(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'message' => 'required|string|max:1600'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $customers = Customer::whereIn('id', $request->customer_ids)
                           ->where('company_id', $company->id)
                           ->where('sms_opt_in', true)
                           ->whereNotNull('phone')
                           ->get();

        if ($customers->isEmpty()) {
            return response()->json(['message' => 'No customers found with SMS opt-in'], 404);
        }

        // Check SMS limits
        if (!$this->checkSmsLimits($company, $customers->count())) {
            return response()->json(['message' => 'SMS limit exceeded'], 429);
        }

        $template = Template::where('id', $request->template_id)
                          ->where('company_id', $company->id)
                          ->where('type', 'sms')
                          ->firstOrFail();

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $smsContent = $this->processTemplate($template, $customer, $company);
            
            try {
                $message = $this->twilio->messages->create(
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
                    'provider_id' => $message->sid
                ]);

                $sent++;

            } catch (\Exception $e) {
                SmsLog::create([
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'message' => $smsContent,
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);

                $failed++;
            }
        }

        return response()->json([
            'message' => "Bulk SMS completed. Sent: $sent, Failed: $failed",
            'sent' => $sent,
            'failed' => $failed
        ]);
    }

    /**
     * Process SMS template with placeholders
     */
    private function processTemplate($template, $customer, $company)
    {
        $content = $template->body_html; // For SMS, this will be plain text

        // Replace customer placeholders
        $content = str_replace('{{customer.name}}', $customer->name, $content);
        $content = str_replace('{{customer.email}}', $customer->email, $content);
        $content = str_replace('{{customer.phone}}', $customer->phone ?? '', $content);

        // Replace company placeholders
        $content = str_replace('{{company.name}}', $company->name, $content);

        // Add branding if not removed (shorter for SMS)
        $subscription = $company->subscription;
        if (!$subscription || !$subscription->canRemoveBranding()) {
            $content .= ' - Powered by Email Zus';
        }

        return $content;
    }

    /**
     * Check SMS sending limits
     */
    private function checkSmsLimits($company, $smsCount = 1)
    {
        $subscription = $company->subscription;
        
        if (!$subscription) {
            // Free tier limits
            $monthlyLimit = 10;
        } else {
            $monthlyLimit = $subscription->getSmsLimit();
        }

        $currentMonthSent = SmsLog::whereHas('customer', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->whereMonth('created_at', now()->month)
        ->where('status', 'sent')
        ->count();

        return ($currentMonthSent + $smsCount) <= $monthlyLimit;
    }

    /**
     * Get SMS statistics
     */
    public function getStats()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $stats = [
            'total_sent' => SmsLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'sent')->count(),
            
            'sent_this_month' => SmsLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'sent')->whereMonth('created_at', now()->month)->count(),
            
            'failed_this_month' => SmsLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'failed')->whereMonth('created_at', now()->month)->count(),
            
            'monthly_limit' => $company->subscription ? $company->subscription->getSmsLimit() : 10,
        ];

        return response()->json($stats);
    }
}
