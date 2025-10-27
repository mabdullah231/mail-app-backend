<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\GoogleOauthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [GoogleOauthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleOauthController::class, 'callback']);