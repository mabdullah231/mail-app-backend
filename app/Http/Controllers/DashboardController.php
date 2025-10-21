<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Template;
use App\Models\EmailLog;
use App\Models\SMSLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user's company
     */
    public function getStats()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        if (!$company) {
            return response()->json([
                'customers' => 0,
                'templates' => 0,
                'emailsSent' => 0,
                'emailsThisMonth' => 0,
                'monthlyLimit' => 0,
                'message' => 'Company details required'
            ], 400);
        }
        $companyId = $company->id;
        
        // Get current month's start and end dates
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        
        // Count customers for this company
        $customersCount = Customer::where('company_id', $companyId)->count();
        
        // Count templates for this company
        $templatesCount = Template::where('company_id', $companyId)->count();
        
        // Count total emails sent
        $emailsSent = EmailLog::where('company_id', $companyId)->count();
        
        // Count emails sent this month
        $emailsThisMonth = EmailLog::where('company_id', $companyId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
        
        // Monthly limit from subscription (fallbacks to 100)
        $monthlyLimit = $company->subscription ? $company->subscription->getEmailLimit() : 100;
        
        return response()->json([
            'customers' => $customersCount,
            'templates' => $templatesCount,
            'emailsSent' => $emailsSent,
            'emailsThisMonth' => $emailsThisMonth,
            'monthlyLimit' => $monthlyLimit
        ]);
    }
}