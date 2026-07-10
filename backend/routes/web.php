<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth must live under the "web" middleware group (not "api") because
// the callback is a top-level browser redirect from Google's servers, not an
// XHR/fetch call from the SPA - it has no Referer/Origin matching our stateful
// domains, so Sanctum's conditional EnsureFrontendRequestsAreStateful never
// starts a session for it under the "api" group. The plain "web" group starts
// a session unconditionally, which Auth::login() needs. Registered under the
// same /api/auth/* path so the frontend and the Google Cloud Console redirect
// URI don't need to change.
Route::prefix('api/auth')->group(function () {
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
});
