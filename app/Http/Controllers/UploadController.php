<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Services\StorageService;
use App\Models\CompanyDetail;

class UploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        try {
            $user = auth()->user();
            
            // Get company details for storage limit check
            $company = CompanyDetail::where('user_id', $user->id)->first();
            
            if ($company) {
                // Check storage limit
                $fileSizeMB = round($request->file('image')->getSize() / 1024 / 1024, 2);
                if (!StorageService::canUpload($company, $fileSizeMB)) {
                    return response()->json([
                        'error' => 'Storage limit exceeded. Please upgrade your plan or delete some files.'
                    ], 413);
                }
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // Create uploads directory if it doesn't exist
            $uploadPath = public_path('uploads/images');
            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }
            
            // Move the file
            $image->move($uploadPath, $imageName);
            
            // Return the URL
            $imageUrl = url('uploads/images/' . $imageName);
            
            return response()->json([
                'success' => true,
                'url' => $imageUrl,
                'filename' => $imageName
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }
}