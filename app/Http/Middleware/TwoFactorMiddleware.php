<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Skip 2FA check for super admins or if 2FA is not enabled
        if (!$user || !$user->google2fa_enabled || $user->user_type == 0) {
            return $next($request);
        }
        
        // Check if 2FA has been verified in this session
        if (!session('2fa_verified')) {
            return response()->json([
                'message' => '2FA verification required',
                'code' => '2FA_REQUIRED'
            ], 403);
        }
        
        return $next($request);
    }
}
