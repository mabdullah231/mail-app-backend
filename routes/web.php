<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\GoogleOAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [GoogleOAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback']);