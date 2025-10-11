<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Models\CompanyDetail;
use App\Services\StorageService;

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
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
            'business_email' => 'nullable|email|max:255',
            'business_email_password' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|string|max:10',
            'smtp_encryption' => 'nullable|string|max:10',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048',
        ]);

        // Create or fetch company
        $company = CompanyDetail::firstOrNew(['user_id' => $user->id]);
        $isNew = !$company->exists;

        // Update all fields
        $company->fill($validated);

        // Handle logo with storage quota check
        if ($request->hasFile('logo')) {
            $fileSizeMB = round($request->file('logo')->getSize() / (1024 * 1024), 2);
            
            if (!StorageService::canUpload($company, $fileSizeMB)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Storage quota exceeded. Please upgrade your plan or delete some files.',
                    'storage_info' => StorageService::getUsageInfo($company)
                ], 413);
            }
            
            // Delete old logo if exists
            if ($company->logo && File::exists(public_path($company->logo))) {
                File::delete(public_path($company->logo));
            }
            
            $logoName = time().'_logo_'.$request->file('logo')->getClientOriginalName();
            $request->file('logo')->move(public_path('company/logos'), $logoName);
            $company->logo = 'company/logos/'.$logoName;
        }

        // Handle signature with storage quota check
        if ($request->hasFile('signature')) {
            $fileSizeMB = round($request->file('signature')->getSize() / (1024 * 1024), 2);
            
            if (!StorageService::canUpload($company, $fileSizeMB)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Storage quota exceeded. Please upgrade your plan or delete some files.',
                    'storage_info' => StorageService::getUsageInfo($company)
                ], 413);
            }
            
            // Delete old signature if exists
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

    public function getStorageInfo()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        $company = CompanyDetail::where('user_id', $user->id)->first();
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 404);
        }

        return response()->json([
            'success' => true,
            'storage_info' => StorageService::getUsageInfo($company)
        ], 200);
    }
}