<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SmsLog;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyDetail;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    /**
     * Send SMS to single customer
     */
    public function sendSingle(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'nullable|exists:templates,id',
            'custom_message' => 'nullable|string|max:1600',
            'message' => 'nullable|string|max:1600' // For backward compatibility
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
        $template = $request->template_id ? Template::findOrFail($request->template_id) : null;

        // Verify ownership and SMS opt-in
        if ($customer->company_id !== $company->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($template && $template->company_id !== $company->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (!$customer->sms_opt_in || !$customer->phone) {
            return response()->json(['message' => 'Customer has not opted in for SMS or no phone number'], 400);
        }

        // Use custom message or template
        if ($request->custom_message) {
            $smsContent = $request->custom_message;
        } elseif ($template) {
            $smsContent = $this->processTemplate($template, $customer, $company);
        } else {
            return response()->json(['message' => 'Either template or custom message is required'], 400);
        }

        try {
            // Mock SMS sending - replace with actual Twilio in production
            Log::info('SMS sendSingle: preparing', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template ? $template->id : null,
                'to' => $customer->phone,
                'message' => $smsContent,
            ]);

            $messageId = $this->sendMockSms($customer->phone, $smsContent);

            // Log the SMS
            SmsLog::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template ? $template->id : null,
                'message' => $smsContent,
                'status' => 'sent',
                'sent_at' => now(),
                'provider_id' => $messageId
            ]);

            Log::info('SMS sendSingle: sent', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template ? $template->id : null,
                'to' => $customer->phone,
                'provider_id' => $messageId,
            ]);

            return response()->json(['message' => 'SMS sent successfully']);

        } catch (\Exception $e) {
            Log::error('SMS sendSingle: failed', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template ? $template->id : null,
                'to' => $customer->phone,
                'error' => $e->getMessage(),
            ]);
            SmsLog::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template ? $template->id : null,
                'message' => $smsContent,
                'status' => 'failed',
                'response' => $e->getMessage()
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
            'template_id' => 'nullable|exists:templates,id',
            'custom_message' => 'nullable|string|max:1600'
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

        $template = $request->template_id ? Template::where('id', $request->template_id)
                          ->where('company_id', $company->id)
                          ->where('type', 'sms')
                          ->first() : null;

        // Validate that we have either template or custom message
        if (!$template && !$request->custom_message) {
            return response()->json(['message' => 'Either template or custom message is required'], 400);
        }

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            // Use custom message or template
            if ($request->custom_message) {
                $smsContent = $request->custom_message;
            } else {
                $smsContent = $this->processTemplate($template, $customer, $company);
            }
            
            try {
                Log::info('SMS sendBulk: preparing', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template ? $template->id : null,
                    'to' => $customer->phone,
                    'message' => $smsContent,
                ]);

                $messageId = $this->sendMockSms($customer->phone, $smsContent);

                SmsLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template ? $template->id : null,
                    'message' => $smsContent,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'provider_id' => $messageId
                ]);

                Log::info('SMS sendBulk: sent', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template ? $template->id : null,
                    'to' => $customer->phone,
                    'provider_id' => $messageId,
                ]);

                $sent++;

            } catch (\Exception $e) {
                Log::error('SMS sendBulk: failed', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template ? $template->id : null,
                    'to' => $customer->phone,
                    'error' => $e->getMessage(),
                ]);
                SmsLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template ? $template->id : null,
                    'message' => $smsContent,
                    'status' => 'failed',
                    'response' => $e->getMessage()
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
     * Mock SMS sending - replace with actual Twilio in production
     */
    private function sendMockSms($phone, $message)
    {
        // Simulate SMS sending delay
        sleep(1);
        
        // For development, just log the SMS instead of actually sending
        Log::info("Mock SMS sent to {$phone}: {$message}");
        
        // Generate a mock message ID
        return 'mock_' . uniqid();
        
        // Uncomment below to use real Twilio when credentials are available
        /*
        $twilio = new \Twilio\Rest\Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );

        $message = $twilio->messages->create(
            $phone,
            [
                'from' => config('services.twilio.phone'),
                'body' => $message
            ]
        );

        return $message->sid;
        */
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
            $content .= ' - Powered by Email ZUS';
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
     * Get SMS logs for authenticated company
     */
    public function getLogs(Request $request)
    {
        $user = Auth::user();
        $company = $user->companyDetail;

        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $query = SmsLog::with(['customer','template'])
            ->where('company_id', $company->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->limit(100)->get();
        return response()->json($logs);
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

    public function exportLogs(Request $request)
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        if (!$company) {
            return response()->json(['message' => 'Company details not found'], 400);
        }

        $query = SMSLog::with(['customer'])
            ->where('company_id', $company->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $filename = 'sms_logs_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            // CSV header
            fputcsv($handle, ['Recipient', 'Message', 'Status', 'Sent At']);
            // Stream rows
            $logs = $query->orderByDesc('created_at')->limit(1000)->get();
            foreach ($logs as $log) {
                $recipient = optional($log->customer)->phone ?? '';
                $message = $log->message ?? '';
                $status = $log->status ?? '';
                // Safely compute sent timestamp without assuming Carbon instance
                $sentAt = '';
                if ($log->sent_at instanceof \Carbon\Carbon || $log->sent_at instanceof \Illuminate\Support\Carbon) {
                    $sentAt = $log->sent_at->format('Y-m-d H:i:s');
                } elseif (!empty($log->sent_at)) {
                    $sentAt = (string) $log->sent_at;
                } elseif ($log->created_at) {
                    $sentAt = $log->created_at->format('Y-m-d H:i:s');
                }
                fputcsv($handle, [$recipient, $message, $status, $sentAt]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}