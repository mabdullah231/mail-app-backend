<?php
namespace App\Http\Controllers;

use App\Models\NotificationRule;
use App\Models\Customer;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationRuleController extends Controller
{
    // List all notification rules for user's company
  // In NotificationRuleController - index method
public function index(Request $request)
{
    try {
        $user = $request->user();
        
        $rules = NotificationRule::with(['customer', 'template'])
            ->whereHas('customer', function($query) use ($user) {
                $query->where('company_id', $user->company_detail->id);
            })
            ->get();
            
        return response()->json($rules);
    } catch (\Exception $e) {
        Log::error('Error fetching notification rules: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch notification rules'], 500);
    }
}

  public function store(Request $request)
{
    try {
        $user = $request->user();
        
        // Debug: Log user and company details
        Log::info('NotificationRule Store - User Details:', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_type' => $user->user_type,
            'company_detail' => $user->company_detail,
            'company_detail_id' => $user->company_detail ? $user->company_detail->id : 'NULL',
            'has_company' => !is_null($user->company_detail)
        ]);

        // Check if user has company details
        if (!$user->company_detail) {
            Log::error('User has no company detail in backend', ['user_id' => $user->id]);
            return response()->json([
                'error' => 'Company setup required. Please complete your company profile first.'
            ], 403);
        }

        // Rest of your code...
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'event_type' => 'required|string|in:birthday,anniversary,appointment,payment_due,custom',
            'timing' => 'required|in:before,on,after',
            'channel' => 'required|in:email,sms',
            'recurring' => 'boolean',
            'recurrence_interval' => 'nullable|string|in:daily,weekly,monthly,yearly',
            'active' => 'boolean',
            'rules' => 'required|array',
            'days_offset' => 'required|integer',
        ]);

        $companyId = $user->company_detail->id;

        // Verify customer belongs to user's company
        $customer = Customer::where('id', $validated['customer_id'])
            ->where('company_id', $companyId)
            ->first();
            
        if (!$customer) {
            return response()->json(['error' => 'Customer not found or access denied'], 403);
        }

        // Verify template belongs to user's company
        $template = Template::where('id', $validated['template_id'])
            ->where('company_id', $companyId)
            ->first();
            
        if (!$template) {
            return response()->json(['error' => 'Template not found or access denied'], 403);
        }

        $rule = NotificationRule::create($validated);
        
        return response()->json([
            'message' => 'Notification rule created successfully',
            'data' => $rule->load(['customer', 'template'])
        ], 201);
        
    } catch (\Exception $e) {
        Log::error('Error creating notification rule: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to create notification rule: ' . $e->getMessage()], 500);
    }
}

    // Show a single notification rule
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $rule = NotificationRule::with(['customer', 'template'])
                ->whereHas('customer', function($query) use ($user) {
                    $query->where('company_id', $user->company_detail->id);
                })
                ->findOrFail($id);
                
            return response()->json($rule);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Notification rule not found'], 404);
        }
    }

    // Update a notification rule
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $rule = NotificationRule::whereHas('customer', function($query) use ($user) {
                $query->where('company_id', $user->company_detail->id);
            })->findOrFail($id);

            $validated = $request->validate([
                'customer_id' => 'sometimes|exists:customers,id',
                'template_id' => 'sometimes|exists:templates,id',
                'event_type' => 'sometimes|string|in:birthday,anniversary,appointment,payment_due,custom',
                'timing' => 'sometimes|in:before,on,after',
                'channel' => 'sometimes|in:email,sms',
                'recurring' => 'boolean',
                'recurrence_interval' => 'nullable|string|in:daily,weekly,monthly,yearly',
                'active' => 'boolean',
                'rules' => 'sometimes|array',
            ]);

            // If customer_id is being updated, verify it belongs to the same company
            if (isset($validated['customer_id'])) {
                $customer = Customer::where('id', $validated['customer_id'])
                    ->where('company_id', $user->company_detail->id)
                    ->first();
                    
                if (!$customer) {
                    return response()->json(['error' => 'Customer not found or access denied'], 403);
                }
            }

            // If template_id is being updated, verify it belongs to the same company
            if (isset($validated['template_id'])) {
                $template = Template::where('id', $validated['template_id'])
                    ->where('company_id', $user->company_detail->id)
                    ->first();
                    
                if (!$template) {
                    return response()->json(['error' => 'Template not found or access denied'], 403);
                }
            }

            $rule->update($validated);
            
            return response()->json([
                'message' => 'Notification rule updated successfully',
                'data' => $rule->load(['customer', 'template'])
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating notification rule: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update notification rule'], 500);
        }
    }

    // Delete a notification rule
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $rule = NotificationRule::whereHas('customer', function($query) use ($user) {
                $query->where('company_id', $user->company_detail->id);
            })->findOrFail($id);
            
            $rule->delete();
            
            return response()->json([
                'message' => 'Notification rule deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting notification rule: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete notification rule'], 500);
        }
    }
}