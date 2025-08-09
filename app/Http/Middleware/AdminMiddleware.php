<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->user_type == 0) { // 0 = Admin
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized access, Only For Admins'], 403);
    }
}
