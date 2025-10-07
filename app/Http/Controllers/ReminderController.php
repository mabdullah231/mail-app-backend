<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Customer;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $query = Reminder::with(['customer','template']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        if ($request->has('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'template_id' => 'required|exists:templates,id',
            'start_at' => 'required|date',
            'recurrence_rule' => 'nullable|array',
            'recurrence_rule.frequency' => 'nullable|string|in:Daily,3 days,Weekly,2 weeks,one-time',
            'recurrence_rule.expires_at' => 'nullable|date|after:start_at',
            'recurrence_rule.notification_rules' => 'nullable|array',
            'next_run_at' => 'nullable|date',
            'active' => 'boolean',
        ]);

        // Set next run date based on start date if not provided
        if (!isset($data['next_run_at'])) {
            $data['next_run_at'] = $data['start_at'];
        }

        $reminder = Reminder::create($data);

        return response()->json($reminder, 201);
    }

    public function show(Reminder $reminder)
    {
        return response()->json($reminder->load('customer','template'));
    }

    public function update(Request $request, Reminder $reminder)
    {
        $data = $request->validate([
            'template_id' => 'sometimes|required|exists:templates,id',
            'start_at' => 'sometimes|required|date',
            'recurrence_rule' => 'nullable|array',
            'recurrence_rule.frequency' => 'nullable|string|in:Daily,3 days,Weekly,2 weeks,one-time',
            'recurrence_rule.expires_at' => 'nullable|date|after:start_at',
            'recurrence_rule.notification_rules' => 'nullable|array',
            'next_run_at' => 'nullable|date',
            'active' => 'boolean',
        ]);

        $reminder->update($data);

        return response()->json($reminder);
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();
        return response()->json(['message' => 'Reminder deleted successfully']);
    }
    
    /**
     * Toggle reminder status (active/inactive)
     */
    public function toggleStatus(Request $request, Reminder $reminder)
    {
        $request->validate([
            'active' => 'required|boolean'
        ]);

        $reminder->update([
            'active' => $request->active
        ]);

        return response()->json([
            'message' => 'Reminder status updated successfully',
            'reminder' => $reminder
        ]);
    }

    /**
     * Get reminders by customer
     */
    public function getByCustomer($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        
        $reminders = Reminder::with(['template'])
            ->where('customer_id', $customerId)
            ->get();

        return response()->json($reminders);
    }

    /**
     * Get reminders by template
     */
    public function getByTemplate($templateId)
    {
        $template = Template::findOrFail($templateId);
        
        $reminders = Reminder::with(['customer'])
            ->where('template_id', $templateId)
            ->get();

        return response()->json($reminders);
    }
}
