<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::with('company');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:company_details,id',
            'title' => 'required|string|max:255',
            'body_html' => 'required|string',
            'placeholders' => 'nullable|array',
            'attachments' => 'nullable|array',
            'type' => 'required|in:email,sms', // Changed to required
            'is_default' => 'boolean',
        ]);

        // Enforce ownership: the authenticated user's company must match
        $user = $request->user();
        $company = $user?->companyDetail;
        if (!$company || (int)$company->id !== (int)$data['company_id']) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Enforce template limit based on subscription or company limits
        $existingCount = Template::where('company_id', $company->id)->count();
        $subscription = $company->subscription;
        $limit = $subscription ? $subscription->getTemplateLimit() : 3;
        if ($existingCount >= $limit) {
            return response()->json(['message' => 'Template limit reached'], 429);
        }

        $template = Template::create($data);

        return response()->json($template, 201);
    }

    public function show(Template $template)
    {
        return response()->json($template->load('company','reminders'));
    }

    public function update(Request $request, Template $template)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body_html' => 'sometimes|required|string',
            'placeholders' => 'nullable|array',
            'attachments' => 'nullable|array',
            'type' => 'sometimes|required|in:email,sms', // Added sometimes|required
            'is_default' => 'boolean',
        ]);

        // Enforce ownership
        $user = $request->user();
        $company = $user?->companyDetail;
        if (!$company || (int)$company->id !== (int)$template->company_id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $template->update($data);

        return response()->json($template);
    }

    public function destroy(Template $template)
    {
        // Enforce ownership
        $user = request()->user();
        $company = $user?->companyDetail;
        if (!$company || (int)$company->id !== (int)$template->company_id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }
}