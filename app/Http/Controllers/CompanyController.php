<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\CompanyDetail;
use App\Models\User;

class CompanyController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validation rules
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'address' => 'required|string|max:500',
                'logo' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048', // 2MB max
                'signature' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find existing company or create new one
            $company = CompanyDetail::where('user_id', $user->id)->first();
            
            if (!$company) {
                $company = new CompanyDetail();
                $company->user_id = $user->id;
            }

            // Update basic details
            $company->name = $request->name;
            $company->address = $request->address;

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($company->logo && file_exists(public_path($company->logo))) {
                    unlink(public_path($company->logo));
                }
                
                // Store new logo directly in public folder
                $logoFile = $request->file('logo');
                $logoName = time() . '_logo_' . $logoFile->getClientOriginalName();
                $logoFile->move(public_path('company/logos'), $logoName);
                $company->logo = 'company/logos/' . $logoName;
            }

            // Handle signature upload
            if ($request->hasFile('signature')) {
                // Delete old signature if exists
                if ($company->signature && file_exists(public_path($company->signature))) {
                    unlink(public_path($company->signature));
                }
                
                // Store new signature directly in public folder
                $signatureFile = $request->file('signature');
                $signatureName = time() . '_signature_' . $signatureFile->getClientOriginalName();
                $signatureFile->move(public_path('company/signatures'), $signatureName);
                $company->signature = 'company/signatures/' . $signatureName;
            }

            // Save company details
            $company->save();

            $user->save();

            return response()->json([
                'success' => true,
                'message' => $company->wasRecentlyCreated ? 'Company details created successfully' : 'Company details updated successfully',
                'company' => $company
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDetails(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $company = CompanyDetail::where('user_id', $user->id)->first();

            return response()->json([
                'success' => true,
                'company' => $company
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }
}