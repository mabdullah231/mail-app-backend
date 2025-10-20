<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class GoogleOauthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
    
        // Find or create user, eager-load company relation
        $user = User::with('companyDetail') // assuming relation is named 'company'
            ->firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name'              => $googleUser->getName(),
                    'password'          => bcrypt(Str::random(16)),
                    'user_type'         => "1",
                    'email_verified_at' => now(),
                    'google_id'         => $googleUser->getId(),
                ]
            );
    
        Auth::login($user);
    
        // Reload with relation in case it was created in firstOrCreate
        $user->load('companyDetail');
    
        $token = $user->createToken('AuthToken')->plainTextToken;
    
        // Add company_detail key explicitly in the object
        $user->company_detail = $user->companyDetail;
    
        return redirect("http://localhost:5173/auth/callback?token={$token}&user=" . urlencode(json_encode($user)));
    }    
}
