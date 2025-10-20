<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use App\Models\Template;
use App\Models\EmailLog;
use App\Models\CompanyDetail;
use App\Models\Subscription;
use App\Mail\CustomEmail;

class EmailController extends Controller
{
    /**
     * Send email to single customer
     */
    public function sendSingle(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'subject' => 'required|string|max:255'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Check email limits
        if (!$this->checkEmailLimits($company)) {
            return response()->json(['message' => 'Email limit exceeded'], 429);
        }

        $customer = Customer::findOrFail($request->customer_id);
        $template = Template::findOrFail($request->template_id);

        // Verify ownership
        if ($customer->company_id !== $company->id || $template->company_id !== $company->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $emailContent = $this->processTemplate($template, $customer, $company);
        
        // Get attachments from template
        $attachments = $template->attachments ?? [];
        
        try {
            Log::info('Email sendSingle: preparing', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $request->subject,
                'to' => $customer->email,
            ]);

            Mail::to($customer->email)
                ->send(new CustomEmail($emailContent, $request->subject, $company, $attachments));

            // Log the email
            EmailLog::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $request->subject,
                'status' => 'sent',
                'sent_at' => now()
            ]);

            Log::info('Email sendSingle: sent', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $request->subject,
                'to' => $customer->email,
            ]);

            return response()->json(['message' => 'Email sent successfully']);

        } catch (\Exception $e) {
            Log::error('Email sendSingle: failed', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $request->subject,
                'to' => $customer->email,
                'error' => $e->getMessage(),
            ]);

            EmailLog::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'template_id' => $template->id,
                'subject' => $request->subject,
                'status' => 'failed',
                'response' => $e->getMessage()
            ]);

            return response()->json(['message' => 'Failed to send email'], 500);
        }
    }

    /**
     * Send email to multiple customers
     */
    public function sendBulk(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'subject' => 'required|string|max:255'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $customers = Customer::whereIn('id', $request->customer_ids)
                           ->where('company_id', $company->id)
                           ->get();

        if ($customers->count() !== count($request->customer_ids)) {
            return response()->json(['message' => 'Some customers not found or access denied'], 403);
        }

        // Check email limits
        if (!$this->checkEmailLimits($company, $customers->count())) {
            return response()->json(['message' => 'Email limit exceeded'], 429);
        }

        $template = Template::where('id', $request->template_id)
                          ->where('company_id', $company->id)
                          ->firstOrFail();

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $emailContent = $this->processTemplate($template, $customer, $company);
            
            // Get attachments from template
            $attachments = $template->attachments ?? [];
            
            try {
                Log::info('Email sendBulk: preparing', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                ]);

                Mail::to($customer->email)
                    ->send(new CustomEmail($emailContent, $request->subject, $company, $attachments));

                EmailLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'status' => 'sent',
                    'sent_at' => now()
                ]);

                Log::info('Email sendBulk: sent', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                ]);

                $sent++;

            } catch (\Exception $e) {
                Log::error('Email sendBulk: failed', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                    'error' => $e->getMessage(),
                ]);

                EmailLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'status' => 'failed',
                    'response' => $e->getMessage()
                ]);

                $failed++;
            }
        }

        return response()->json([
            'message' => "Bulk email completed. Sent: $sent, Failed: $failed",
            'sent' => $sent,
            'failed' => $failed
        ]);
    }

    /**
     * Send to all customers
     */
    public function sendToAll(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:templates,id',
            'subject' => 'required|string|max:255'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $customers = Customer::where('company_id', $company->id)->get();

        if ($customers->isEmpty()) {
            return response()->json(['message' => 'No customers found'], 404);
        }

        // Check email limits
        if (!$this->checkEmailLimits($company, $customers->count())) {
            return response()->json(['message' => 'Email limit exceeded'], 429);
        }

        $template = Template::where('id', $request->template_id)
                          ->where('company_id', $company->id)
                          ->firstOrFail();

        $sent = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $emailContent = $this->processTemplate($template, $customer, $company);
            
            // Get attachments from template
            $attachments = $template->attachments ?? [];
            
            try {
                Log::info('Email sendToAll: preparing', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                ]);

                Mail::to($customer->email)
                    ->send(new CustomEmail($emailContent, $request->subject, $company, $attachments));

                EmailLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'status' => 'sent',
                    'sent_at' => now()
                ]);

                Log::info('Email sendToAll: sent', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                ]);

                $sent++;

            } catch (\Exception $e) {
                Log::error('Email sendToAll: failed', [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'to' => $customer->email,
                    'error' => $e->getMessage(),
                ]);

                EmailLog::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'template_id' => $template->id,
                    'subject' => $request->subject,
                    'status' => 'failed',
                    'response' => $e->getMessage()
                ]);

                $failed++;
            }
        }

        return response()->json([
            'message' => "Email sent to all customers. Sent: $sent, Failed: $failed",
            'sent' => $sent,
            'failed' => $failed
        ]);
    }

    /**
     * Process template with placeholders
     */
    private function processTemplate($template, $customer, $company)
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

        // Also replace generic placeholders and whitespace-tolerant variants
        $placeholderMap = [
            // explicit dot-notated keys
            'customer.name' => $customer->name,
            'customer.email' => $customer->email,
            'customer.phone' => $customer->phone ?? '',
            'customer.address' => $customer->address ?? '',
            'customer.country' => $customer->country ?? '',
            'company.name' => $company->name,
            'company.address' => $company->address,
            // generic synonyms often used in templates
            'name' => $customer->name,
            'email' => $customer->email,
            'company' => $company->name,
            // sender/recipient synonyms
            'recipient.name' => $customer->name,
            'recipient.email' => $customer->email,
            'sender.name' => $company->name,
            'sender.email' => $company->business_email ?? config('mail.from.address'),
        ];
        foreach ($placeholderMap as $key => $value) {
            $content = preg_replace('/{{\s*' . preg_quote($key, '/') . '\s*}}/i', $value ?? '', $content);
        }

        // Add company logo if exists
        if ($company->logo) {
            $logoUrl = url($company->logo);
            $content = str_replace('{{company.logo}}', "<img src='$logoUrl' alt='Company Logo' style='max-width: 200px;'>", $content);
            // whitespace-tolerant variant
            $content = preg_replace('/{{\s*company\.logo\s*}}/i', "<img src='$logoUrl' alt='Company Logo' style='max-width: 200px;'>", $content);
        }

        // Add company signature if exists
        if ($company->signature) {
            $signatureUrl = url($company->signature);
            $content = str_replace('{{company.signature}}', "<img src='$signatureUrl' alt='Signature' style='max-width: 300px;'>", $content);
            // whitespace-tolerant variant
            $content = preg_replace('/{{\s*company\.signature\s*}}/i', "<img src='$signatureUrl' alt='Signature' style='max-width: 300px;'>", $content);
        }

        // Add branding if not removed
        $subscription = $company->subscription;
        if (!$subscription || !$subscription->canRemoveBranding()) {
            $content .= '<br><br><p style="font-size: 12px; color: #666; text-align: center;">Powered by <a href="https://emailzus.com" style="color: #007bff;">Email Zus</a></p>';
        }

        return $content;
    }

    /**
     * Check email sending limits
     */
    private function checkEmailLimits($company, $emailCount = 1)
    {
        $subscription = $company->subscription;
        
        if (!$subscription) {
            // Free tier limits
            $monthlyLimit = 100;
        } else {
            $monthlyLimit = $subscription->getEmailLimit();
        }

        $currentMonthSent = EmailLog::whereHas('customer', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->whereMonth('created_at', now()->month)
        ->where('status', 'sent')
        ->count();

        return ($currentMonthSent + $emailCount) <= $monthlyLimit;
    }

    /**
     * Get email statistics
     */
    public function getStats()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $stats = [
            'total_sent' => EmailLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'sent')->count(),
            
            'sent_this_month' => EmailLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'sent')->whereMonth('created_at', now()->month)->count(),
            
            'failed_this_month' => EmailLog::whereHas('customer', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })->where('status', 'failed')->whereMonth('created_at', now()->month)->count(),
            
            'monthly_limit' => $company->subscription ? $company->subscription->getEmailLimit() : 100,
        ];

        return response()->json($stats);
    }
}
