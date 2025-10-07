<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\CompanyDetail;
use App\Models\Customer;
use App\Models\Template;
use App\Models\EmailLog;
use App\Models\Subscription;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmailsExport;

class SuperAdminController extends Controller
{
    /**
     * Get platform statistics
     */
    public function getDashboardStats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_companies' => CompanyDetail::count(),
            'total_customers' => Customer::count(),
            'total_templates' => Template::count(),
            'emails_sent_today' => EmailLog::whereDate('created_at', today())->count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'revenue_this_month' => Subscription::whereMonth('created_at', now()->month)
                                              ->where('status', 'active')
                                              ->sum('amount')
        ];

        return response()->json($stats);
    }

    /**
     * Get all users with company details
     */
    public function getAllUsers(Request $request)
    {
        $query = User::with(['companyDetail', 'companyDetail.subscription']);

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->paginate(20);

        return response()->json($users);
    }

    /**
     * Ban/Unban user
     */
    public function toggleUserStatus(Request $request, $userId)
    {
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $user = User::findOrFail($userId);
        
        if ($user->user_type == 0) {
            return response()->json(['message' => 'Cannot modify super admin account'], 403);
        }

        $user->update(['is_active' => $request->is_active]);

        return response()->json([
            'message' => $request->is_active ? 'User activated' : 'User banned',
            'user' => $user
        ]);
    }

    /**
     * Delete user and all associated data
     */
    public function deleteUser($userId)
    {
        $user = User::findOrFail($userId);
        
        if ($user->user_type == 0) {
            return response()->json(['message' => 'Cannot delete super admin account'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Limit business account functions
     */
    public function limitBusinessAccount(Request $request, $userId)
    {
        $request->validate([
            'email_limit' => 'nullable|integer|min:0',
            'template_limit' => 'nullable|integer|min:0',
            'sms_limit' => 'nullable|integer|min:0',
            'storage_limit' => 'nullable|integer|min:0',
            'features_disabled' => 'nullable|array'
        ]);

        $user = User::findOrFail($userId);
        
        if ($user->user_type != 1) {
            return response()->json(['message' => 'Can only limit business accounts'], 403);
        }

        $company = $user->companyDetail;
        if (!$company) {
            return response()->json(['message' => 'Company details not found'], 404);
        }

        // Update company limits
        $company->update([
            'email_limit' => $request->email_limit ?? $company->email_limit,
            'template_limit' => $request->template_limit ?? $company->template_limit,
            'sms_limit' => $request->sms_limit ?? $company->sms_limit,
            'storage_limit' => $request->storage_limit ?? $company->storage_limit,
            'features_disabled' => $request->features_disabled ?? $company->features_disabled
        ]);

        return response()->json([
            'message' => 'Business account limits updated successfully',
            'company' => $company
        ]);
    }

    /**
     * Get all emails from all companies
     */
    public function getAllEmails(Request $request)
    {
        $query = Customer::with(['company.user'])
                        ->select('email', 'name', 'company_id', 'created_at');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $emails = $query->paginate(50);

        return response()->json($emails);
    }

    /**
     * Export all emails to Excel
     */
    public function exportEmails()
    {
        return Excel::download(new EmailsExport, 'all_emails_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Get all templates from all companies
     */
    public function getAllTemplates(Request $request)
    {
        $query = Template::with(['company.user']);

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $templates = $query->paginate(20);

        return response()->json($templates);
    }

    /**
     * Set limits for a company
     */
    public function setCompanyLimits(Request $request, $companyId)
    {
        $request->validate([
            'email_limit' => 'required|integer|min:0',
            'template_limit' => 'required|integer|min:1',
            'sms_limit' => 'required|integer|min:0',
            'storage_limit_mb' => 'required|integer|min:10'
        ]);

        $company = CompanyDetail::findOrFail($companyId);
        
        $subscription = $company->subscription ?? new Subscription(['company_id' => $companyId]);
        
        $subscription->limits = [
            'emails_per_month' => $request->email_limit,
            'templates' => $request->template_limit,
            'sms_per_month' => $request->sms_limit,
            'storage_mb' => $request->storage_limit_mb
        ];
        
        $subscription->save();

        return response()->json([
            'message' => 'Company limits updated successfully',
            'subscription' => $subscription
        ]);
    }

    /**
     * Get email logs for monitoring
     */
    public function getEmailLogs(Request $request)
    {
        $query = EmailLog::with(['customer.company.user']);

        if ($request->has('company_id')) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($logs);
    }
}
