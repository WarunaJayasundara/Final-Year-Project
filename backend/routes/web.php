<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// In production the built frontend (frontend/dist) is copied into public/ at
// image-build time, so this serves the SPA's index.html for every non-API
// GET request (client-side routing handles the rest). Falls back to the
// default Laravel welcome view in local dev, where the frontend is served
// separately by the Vite dev server and public/index.html doesn't exist.
Route::get('/{any?}', function () {
    $spaIndex = public_path('index.html');

    return file_exists($spaIndex) ? response()->file($spaIndex) : view('welcome');
})->where('any', '^(?!api|sanctum|storage).*$');

// Google OAuth has to live under "web", not "api": the callback is a
// top-level browser redirect from Google, not an XHR call from the SPA, so
// it has no Referer/Origin matching our stateful domains and Sanctum's
// conditional EnsureFrontendRequestsAreStateful would never start a session
// for it under "api". "web" starts a session unconditionally, which
// Auth::login() needs. Kept under /api/auth/* so the frontend and the
// Google Cloud Console redirect URI don't need to change.
Route::prefix('api/auth')->group(function () {
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
});
