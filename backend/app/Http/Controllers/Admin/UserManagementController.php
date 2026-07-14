<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    /**
     * List / search users (any admin).
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->with('currentLevel')
            ->withCount([
                'testSessions as daily_sessions_completed_count' => fn ($q) => $q->where('session_type', 'daily')->whereNotNull('completed_at'),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($users);
    }

    /**
     * Create a new admin/super_admin account (super_admin only, enforced by route middleware).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,super_admin'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'auth_provider' => 'password',
        ]);

        return response()->json(['user' => $user], 201);
    }

    /**
     * Promote/demote a user's role (super_admin only).
     */
    public function updateRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:super_admin,admin,user'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($user->role === 'super_admin' && $request->input('role') !== 'super_admin') {
            $remainingSuperAdmins = User::where('role', 'super_admin')->where('id', '!=', $user->id)->count();
            if ($remainingSuperAdmins === 0) {
                return response()->json(['message' => 'Cannot demote the last remaining super admin.'], 422);
            }
        }

        $user->update(['role' => $request->input('role')]);

        return response()->json(['user' => $user->fresh()]);
    }

    /**
     * Delete a user account (super_admin only).
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($user->role === 'super_admin') {
            $remainingSuperAdmins = User::where('role', 'super_admin')->where('id', '!=', $user->id)->count();
            if ($remainingSuperAdmins === 0) {
                return response()->json(['message' => 'Cannot delete the last remaining super admin.'], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
