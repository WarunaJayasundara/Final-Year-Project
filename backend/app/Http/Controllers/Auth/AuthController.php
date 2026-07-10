<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Redirect the SPA to Google's OAuth consent screen.
     */
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle the callback from Google, find-or-create the user, log them in,
     * then redirect back to the frontend SPA.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect(env('FRONTEND_URL').'/login?error=google_auth_failed');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Student',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'auth_provider' => 'google',
                'role' => 'user',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect(env('FRONTEND_URL').'/auth/callback');
    }

    /**
     * Admin / super-admin email+password login. Google accounts cannot use this.
     */
    public function adminLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->input('email'))
            ->whereIn('role', ['admin', 'super_admin'])
            ->first();

        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['user' => $this->formatUser($user)]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['user' => null], 200);
        }

        return response()->json(['user' => $this->formatUser($user)]);
    }

    public function updateLocale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'locale' => ['required', 'in:en,si'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $user->update(['locale' => $request->input('locale')]);

        return response()->json(['user' => $this->formatUser($user)]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role,
            'locale' => $user->locale,
            'current_level_id' => $user->current_level_id,
            'placement_completed_at' => $user->placement_completed_at,
        ];
    }
}
