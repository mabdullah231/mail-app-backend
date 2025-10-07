<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'userType' => 'required|in:0,1,2',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $logoPath = null;
        
        // Handle company logo upload
        if ($request->hasFile('company_logo')) {
            $image = $request->file('company_logo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        
            $destinationPath = public_path('company_logos');
        
            // Create the directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
        
            // Move the file to the public path
            $image->move($destinationPath, $imageName);
        
            // Save relative path to DB
            $logoPath = 'company_logos/' . $imageName;
        }
        

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->userType,
            'company_logo' => $logoPath,
        ]);

        $this->sendCode($user->id);

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'user_id' => $user->id
        ], 200);
    }

    public function sendCode($user_id)
    {
        $user = User::find($user_id);
        $code = rand(100000, 999999);
        
        // TODO: Uncomment when email job is ready
        // VerifyEmailJob::dispatch(['email' => $user->email, 'code' => $code]);
        
        $user->update(['code' => $code]);
        
        return $code; // For testing purposes, remove in production
    }

    public function resendEmail(Request $request)
    {
        $request->validate(['user_id' => 'required|numeric']);
        
        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        
        $this->sendCode($request->user_id);
        
        return response()->json(['message' => 'Verification code resent.'], 200);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6|numeric',
            'user_id' => 'required|numeric'
        ]);
        
        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        
        if ($user->code == $request->code) {
            $user->update([
                'email_verified_at' => now(),
                'code' => null
            ]);
            
            $token = $user->createToken('user_token')->plainTextToken;
            
            return response()->json([
                'message' => 'Email verified.',
                'user' => $user->load('companyDetail'), // Load company relationship if exists
                'token' => $token
            ], 200);
        }
        
        return response()->json(['message' => 'Invalid verification code.'], 422);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::find(Auth::id());

            if (!$user->hasVerifiedEmail()) {
                $this->sendCode($user->id);
                Auth::logout();
                return response()->json([
                    'message' => 'Verify your email.',
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'user_id' => $user->id
                ], 422);
            }
            
            // if (!$user->is_active) {
            //     Auth::logout();
            //     return response()->json([
            //         'message' => 'Contact admin, your account is inactive.'
            //     ], 403);
            // }
            
            $token = $user->createToken('user_token')->plainTextToken;
            
            return response()->json([
                'message' => 'Logged in.',
                'user' => $user->load('companyDetail'),
                'token' => $token
            ], 200);
        }
        
        return response()->json(['message' => 'Invalid credentials.'], 422);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        
        if ($user) {
            $this->sendCode($user->id);
            return response()->json([
                'message' => 'OTP sent.',
                'user_id' => $user->id
            ], 200);
        }
        
        return response()->json(['message' => 'Email not found.'], 422);
    }

    public function verifyForgotPassword(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6|numeric',
            'user_id' => 'required|numeric'
        ]);
        
        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        
        if ($user->code == $request->code) {
            $user->update([
                'email_verified_at' => now(),
                'code' => null
            ]);
            
            return response()->json([
                'message' => 'Email verified. Set new password.',
                'user_id' => $user->id
            ], 200);
        }
        
        return response()->json(['message' => 'Invalid verification code.'], 422);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|numeric',
            'password' => 'required|min:8|confirmed',
        ]);
        
        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        
        $user->update([
            'password' => Hash::make($request->password)
        ]);
        
        return response()->json([
            'message' => 'Password reset successfully.'
        ], 200);
    }

    /**
     * Update user profile with company logo
     */

    
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . Auth::id(),
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        $user = Auth::user();
        $data = $request->only(['name', 'email']);
    
        User::where('id', Auth::id())->update($data);
    
        // Refresh user
        $user = Auth::user();
    
        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user
        ], 200);
    }
    

    /**
     * Get user profile with company logo URL
     */
    public function getProfile()
{
    $user = Auth::user();

    if ($user->company_logo) {
        $user->company_logo_url = url($user->company_logo);
    }

    return response()->json([
        'user' => $user
    ], 200);
}

}