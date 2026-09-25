<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// In production the built frontend (frontend/dist) is copied into public/ at image-build time.
Route::get('/{any?}', function () {
    $spaIndex = public_path('index.html');

    return file_exists($spaIndex) ? response()->file($spaIndex) : view('welcome');
})->where('any', '^(?!api|sanctum|storage).*$');

// Google OAuth has to live under "web", not "api": the callback is a top-level browser redirect from Google.
Route::prefix('api/auth')->group(function () {
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
});
