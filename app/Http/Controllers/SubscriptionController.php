<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\CompanyDetail;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Get current subscription details
     */
    public function getCurrent()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $subscription = $company->subscription;
        
        if (!$subscription) {
            // Return free tier details
            return response()->json([
                'plan_type' => 'free',
                'remove_branding' => false,
                'limits' => [
                    'emails_per_month' => 100,
                    'templates' => 3,
                    'sms_per_month' => 10
                ],
                'status' => 'active'
            ]);
        }

        return response()->json($subscription);
    }

    /**
     * Subscribe to remove branding
     */
    public function subscribeBrandingRemoval(Request $request)
    {
        $request->validate([
            'plan_duration' => 'required|in:1,3,6,12', // months
            'payment_id' => 'required|string' // PayPal/Stripe transaction ID
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Pricing based on your requirements
        $pricing = [
            1 => 5.00,   // $5 per month
            3 => 14.00,  // $14 for 3 months
            6 => 26.00,  // $26 for 6 months
            12 => 50.00  // $50 for 12 months
        ];

        $duration = $request->plan_duration;
        $amount = $pricing[$duration];

        // Create or update subscription
        $subscription = $company->subscription ?? new Subscription(['company_id' => $company->id]);
        
        $subscription->plan_type = $duration == 1 ? 'monthly' : $duration . '_months';
        $subscription->amount = $amount;
        $subscription->starts_at = now();
        $subscription->expires_at = now()->addMonths($duration);
        $subscription->remove_branding = true;
        $subscription->status = 'active';
        $subscription->payment_id = $request->payment_id;
        
        // Set limits (same as free for branding-only subscription)
        $subscription->limits = [
            'emails_per_month' => 1000, // Increased for paid users
            'templates' => 20,
            'sms_per_month' => 100
        ];

        $subscription->save();

        return response()->json([
            'message' => 'Subscription activated successfully',
            'subscription' => $subscription
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company || !$company->subscription) {
            return response()->json(['message' => 'No active subscription found'], 404);
        }

        $subscription = $company->subscription;
        $subscription->status = 'cancelled';
        $subscription->save();

        return response()->json([
            'message' => 'Subscription cancelled successfully'
        ]);
    }

    /**
     * Get pricing information
     */
    public function getPricing()
    {
        $pricing = [
            'branding_removal' => [
                '1_month' => ['price' => 5.00, 'duration' => '1 month'],
                '3_months' => ['price' => 14.00, 'duration' => '3 months', 'savings' => '7%'],
                '6_months' => ['price' => 26.00, 'duration' => '6 months', 'savings' => '13%'],
                '12_months' => ['price' => 50.00, 'duration' => '12 months', 'savings' => '17%']
            ],
            'features' => [
                'free' => [
                    'emails_per_month' => 100,
                    'templates' => 3,
                    'sms_per_month' => 10,
                    'branding' => 'Powered by Email Zus'
                ],
                'paid' => [
                    'emails_per_month' => 1000,
                    'templates' => 20,
                    'sms_per_month' => 100,
                    'branding' => 'Removed'
                ]
            ]
        ];

        return response()->json($pricing);
    }

    /**
     * Check if subscription is expired and update status
     */
    public function checkExpiration()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company || !$company->subscription) {
            return response()->json(['message' => 'No subscription found']);
        }

        $subscription = $company->subscription;
        
        if ($subscription->expires_at && $subscription->expires_at->isPast() && $subscription->status === 'active') {
            $subscription->status = 'expired';
            $subscription->remove_branding = false;
            $subscription->save();

            return response()->json([
                'message' => 'Subscription expired',
                'subscription' => $subscription
            ]);
        }

        return response()->json([
            'message' => 'Subscription is active',
            'subscription' => $subscription
        ]);
    }
}
