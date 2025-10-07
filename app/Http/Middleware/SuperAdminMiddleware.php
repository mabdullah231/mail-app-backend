<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check authentication first
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Allow only Super Admins (user_type == "0")
        if (Auth::user()->user_type !== "0") {
            return response()->json(['message' => 'Access denied. Super admin only.'], 403);
        }

        return $next($request);
    }
}
