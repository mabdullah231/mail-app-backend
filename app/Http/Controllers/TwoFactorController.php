<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate QR code for 2FA setup
     */
    public function generateSecret(Request $request)
    {
        $user = Auth::user();
        
        // Generate secret key
        $secretKey = $this->google2fa->generateSecretKey();
        
        // Store temporarily (not enabled yet)
        $user->update(['google2fa_secret' => $secretKey]);
        
        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secretKey
        );
        
        return response()->json([
            'secret' => $secretKey,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $secretKey
        ]);
    }

    /**
     * Verify and enable 2FA
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = Auth::user();
        
        if (!$user->google2fa_secret) {
            return response()->json(['message' => 'Please generate secret first'], 400);
        }

        // Verify the code
        $valid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        
        if ($valid) {
            $user->update(['google2fa_enabled' => true]);
            return response()->json(['message' => '2FA enabled successfully']);
        }
        
        return response()->json(['message' => 'Invalid verification code'], 422);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = Auth::user();
        
        if (!$user->google2fa_enabled || !$user->google2fa_secret) {
            return response()->json(['message' => '2FA not enabled'], 400);
        }

        // Verify the code before disabling
        $valid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        
        if ($valid) {
            $user->update([
                'google2fa_enabled' => false,
                'google2fa_secret' => null
            ]);
            return response()->json(['message' => '2FA disabled successfully']);
        }
        
        return response()->json(['message' => 'Invalid verification code'], 422);
    }

    /**
     * Verify 2FA code during login
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = Auth::user();
        
        if (!$user->google2fa_enabled || !$user->google2fa_secret) {
            return response()->json(['message' => '2FA not enabled'], 400);
        }

        $valid = $this->google2fa->verifyKey($user->google2fa_secret, $request->code);
        
        if ($valid) {
            // Store 2FA verification in session
            session(['2fa_verified' => true]);
            return response()->json(['message' => '2FA verified successfully']);
        }
        
        return response()->json(['message' => 'Invalid verification code'], 422);
    }

    /**
     * Get 2FA status
     */
    public function status()
    {
        $user = Auth::user();
        
        return response()->json([
            'enabled' => $user->google2fa_enabled,
            'has_secret' => !empty($user->google2fa_secret)
        ]);
    }
}
