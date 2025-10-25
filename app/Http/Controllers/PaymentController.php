<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\CompanyDetail;
use Carbon\Carbon;
use Stripe\StripeClient;

class PaymentController extends Controller
{
    /**
     * Create PayPal payment session
     */
    public function createPayPalPayment(Request $request)
    {
        $request->validate([
            'plan_duration' => 'required|in:1,3,6,12',
            'amount' => 'required|numeric|min:0'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Pricing validation
        $pricing = [
            1 => 5.00,
            3 => 14.00,
            6 => 26.00,
            12 => 50.00
        ];

        if ($request->amount != $pricing[$request->plan_duration]) {
            return response()->json(['message' => 'Invalid amount'], 400);
        }

        // Create payment session (you'll need to integrate with PayPal SDK)
        $paymentData = [
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'paypal'
            ],
            'transactions' => [
                [
                    'amount' => [
                        'total' => $request->amount,
                        'currency' => 'USD'
                    ],
                    'description' => 'Email Zus Branding Removal - ' . $request->plan_duration . ' month(s)',
                    'custom' => json_encode([
                        'company_id' => $company->id,
                        'plan_duration' => $request->plan_duration,
                        'user_id' => $user->id
                    ])
                ]
            ],
            'redirect_urls' => [
                'return_url' => config('app.frontend_url') . '/payment/success',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel'
            ]
        ];

        // Store pending payment
        session(['pending_payment' => $paymentData]);

        return response()->json([
            'message' => 'Payment session created',
            'payment_data' => $paymentData
        ]);
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(Request $request)
    {
        // Verify PayPal webhook signature
        $headers = $request->headers->all();
        $body = $request->getContent();
        
        // You'll need to implement PayPal webhook verification
        // For now, we'll process the payment
        
        $event = json_decode($body, true);
        
        if ($event['event_type'] === 'PAYMENT.SALE.COMPLETED') {
            $this->processPayment($event['resource']);
        }
        
        return response()->json(['status' => 'success']);
    }

    /**
     * Create Stripe payment session
     */
    public function createStripePayment(Request $request)
    {
        $request->validate([
            'plan_duration' => 'required|in:1,3,6,12',
            'amount' => 'required|numeric|min:0'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Pricing validation
        $pricing = [
            1 => 5.00,
            3 => 14.00,
            6 => 26.00,
            12 => 50.00
        ];

        if (!isset($pricing[$request->plan_duration])) {
            return response()->json(['message' => 'Invalid plan duration'], 400);
        }

        if ($request->amount != $pricing[$request->plan_duration]) {
            return response()->json(['message' => 'Invalid amount'], 400);
        }

        // Use configured Stripe secret to create a PaymentIntent
        $stripeSecret = config('services.stripe.secret');
        if (empty($stripeSecret)) {
            return response()->json(['message' => 'Stripe secret not configured'], 500);
        }

        $stripe = new StripeClient($stripeSecret);

        $amountCents = (int) round($pricing[$request->plan_duration] * 100);

        $intent = $stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'company_id' => $company->id,
                'plan_duration' => (string) $request->plan_duration,
                'user_id' => (string) $user->id
            ],
            'description' => 'Email Zus Branding Removal - ' . $request->plan_duration . ' month(s)'
        ]);

        return response()->json([
            'message' => 'Stripe PaymentIntent created',
            'payment_intent_id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'amount' => $amountCents,
        ]);
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        
        // Verify Stripe webhook signature
        // You'll need to implement Stripe webhook verification
        
        $event = json_decode($payload, true);
        
        if ($event['type'] === 'payment_intent.succeeded') {
            $this->processPayment($event['data']['object']);
        }
        
        return response()->json(['status' => 'success']);
    }

    /**
     * Process successful payment
     */
    private function processPayment($paymentData)
    {
        $metadata = $paymentData['metadata'] ?? json_decode($paymentData['custom'] ?? '{}', true);
        
        $companyId = $metadata['company_id'];
        $planDuration = $metadata['plan_duration'];
        
        $company = CompanyDetail::find($companyId);
        if (!$company) {
            return;
        }

        // Create or update subscription
        $subscription = $company->subscription ?? new Subscription(['company_id' => $companyId]);
        
        $subscription->plan_type = $planDuration == 1 ? 'monthly' : $planDuration . '_months';
        $subscription->amount = $paymentData['amount'] / 100; // Convert from cents
        $subscription->starts_at = now();
        $subscription->expires_at = now()->addMonths($planDuration);
        $subscription->remove_branding = true;
        $subscription->status = 'active';
        $subscription->payment_id = $paymentData['id'];
        
        // Set limits
        $subscription->limits = [
            'emails_per_month' => 1000,
            'templates' => 20,
            'sms_per_month' => 100,
            'storage_mb' => 500
        ];

        $subscription->save();
    }

    /**
     * Create donation payment
     */
    public function createDonationPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:1000',
            'donor_name' => 'nullable|string|max:255',
            'donor_email' => 'nullable|email',
            'message' => 'nullable|string|max:500'
        ]);

        $donationData = [
            'amount' => $request->amount,
            'donor_name' => $request->donor_name,
            'donor_email' => $request->donor_email,
            'message' => $request->message,
            'currency' => 'USD',
            'description' => 'Donation to Email Zus'
        ];

        return response()->json([
            'message' => 'Donation payment session created',
            'donation_data' => $donationData
        ]);
    }

    /**
     * Handle donation webhook
     */
    public function handleDonationWebhook(Request $request)
    {
        $event = json_decode($request->getContent(), true);
        
        if ($event['type'] === 'payment_intent.succeeded') {
            $this->processDonation($event['data']['object']);
        }
        
        return response()->json(['status' => 'success']);
    }

    /**
     * Process donation
     */
    private function processDonation($paymentData)
    {
        // Log donation (you might want to create a donations table)
        \Log::info('Donation received', [
            'amount' => $paymentData['amount'] / 100,
            'metadata' => $paymentData['metadata'] ?? []
        ]);
    }
}
