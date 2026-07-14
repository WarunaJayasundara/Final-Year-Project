<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
     * Find-or-create the user from Google's callback, log them in, redirect
     * back to the SPA.
     *
     * Account linking: matched by google_id first, then by email. Since the
     * email Socialite returns is already provider-verified by Google, it's
     * safe to attach google_id to a matching password account. We never do
     * the reverse (auto-attaching a password to a Google-only account) -
     * only this direction, only from this callback.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Google OAuth callback failed.', ['error' => $e->getMessage()]);

            return redirect(env('FRONTEND_URL').'/login?error=google_auth_failed');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar_url' => $user->avatar_url ?: $googleUser->getAvatar(),
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
     * Student self-registration with username + email + password. If this
     * email later signs in with Google, handleGoogleCallback() links it
     * rather than creating a duplicate account.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'date_of_birth' => ['required', 'date', 'before:-10 years', 'after:-120 years'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'date_of_birth.before' => 'You must be at least 10 years old to register.',
            'date_of_birth.after' => 'Please enter a valid date of birth.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'date_of_birth' => $request->input('date_of_birth'),
            'password' => Hash::make($request->input('password')),
            'auth_provider' => 'password',
            'role' => 'user',
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['user' => $this->formatUser($user)], 201);
    }

    /**
     * Student sign-in via username-or-email + password. Google-only accounts
     * (no password set) cannot use this path - they get a clear error
     * pointing them at "Continue with Google" instead.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $identifier = $request->input('identifier');
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user || $user->isAdmin()) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->password) {
            return response()->json(['message' => 'This account signs in with Google. Use "Continue with Google" instead.'], 422);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['user' => $this->formatUser($user)]);
    }

    /**
     * Send a password-reset link. Always returns a generic success message
     * regardless of whether the account exists, so this endpoint can't be
     * used to enumerate registered emails.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password has been reset. You can now sign in.']);
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
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'auth_provider' => $user->auth_provider,
            'role' => $user->role,
            'locale' => $user->locale,
            'current_level_id' => $user->current_level_id,
            'placement_completed_at' => $user->placement_completed_at,
        ];
    }
}
