<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Template;
use App\Models\Email;
use App\Models\EmailLog;
use App\Models\SMS;
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
        $companyId = $user->company_id;
        
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
        
        // Get monthly limit (could be from a settings table or subscription)
        $monthlyLimit = 100; // Default value, replace with actual limit from subscription
        
        return response()->json([
            'customers' => $customersCount,
            'templates' => $templatesCount,
            'emailsSent' => $emailsSent,
            'emailsThisMonth' => $emailsThisMonth,
            'monthlyLimit' => $monthlyLimit
        ]);
    }
}