<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Models\CompanyDetail;

class CompanyController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
        ]);

        // Create or fetch company
        $company = CompanyDetail::firstOrNew(['user_id' => $user->id]);
        $isNew = !$company->exists;

        $company->name = $validated['name'];
        $company->address = $validated['address'];

        // Handle logo
        if ($request->hasFile('logo')) {
            if ($company->logo && File::exists(public_path($company->logo))) {
                File::delete(public_path($company->logo));
            }
            $logoName = time().'_logo_'.$request->file('logo')->getClientOriginalName();
            $request->file('logo')->move(public_path('company/logos'), $logoName);
            $company->logo = 'company/logos/'.$logoName;
        }

        // Handle signature
        if ($request->hasFile('signature')) {
            if ($company->signature && File::exists(public_path($company->signature))) {
                File::delete(public_path($company->signature));
            }
            $sigName = time().'_signature_'.$request->file('signature')->getClientOriginalName();
            $request->file('signature')->move(public_path('company/signatures'), $sigName);
            $company->signature = 'company/signatures/'.$sigName;
        }

        $company->save();

        return response()->json([
            'success' => true,
            'message' => $isNew ? 'Company details created successfully' : 'Company details updated successfully',
            'company' => $company
        ], $isNew ? 201 : 200);
    }

    public function getDetails()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $company = CompanyDetail::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'company' => $company
        ], 200);
    }
}
