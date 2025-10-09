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
        // Log::info('template comes', ['data' => $request->all()]);
        $data = $request->validate([
            'company_id' => 'required|exists:company_details,id',
            'title' => 'required|string|max:255',
            'body_html' => 'required|string',
            'placeholders' => 'nullable|array',
            'attachments' => 'nullable|array',
            'type' => 'in:email,sms',
            'is_default' => 'boolean',
        ]);

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
            'type' => 'in:email,sms',
            'is_default' => 'boolean',
        ]);

        $template->update($data);

        return response()->json($template);
    }

    public function destroy(Template $template)
    {
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }
}
