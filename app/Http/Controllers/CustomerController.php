<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('company');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:company_details,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'sms_opt_in' => 'boolean',
            'notification' => 'in:email,sms,both,none',
            'template_id' => 'nullable|exists:templates,id',
            'frequency' => 'in:daily,3 days,weekly,2 weeks,monthly,quarterly,yearly',
            'reminder_start_date' => 'nullable|date',
            'notification_rules' => 'nullable|array',
            'unsubscribe_option' => 'boolean',
        ]);

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer->load('company','reminders'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'sms_opt_in' => 'boolean',
            'notification' => 'in:email,sms,both,none',
            'template_id' => 'nullable|exists:templates,id',
            'frequency' => 'in:daily,3 days,weekly,2 weeks,monthly,quarterly,yearly',
            'reminder_start_date' => 'nullable|date',
            'reminder_days_before' => 'nullable|integer|min:0|max:90',
            'reminder_days_after' => 'nullable|integer|min:0|max:90',
            'unsubscribe_option' => 'boolean',
        ]);

        $customer->update($data);

        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
