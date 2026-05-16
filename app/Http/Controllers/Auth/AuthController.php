<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateUserNameRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $user->assignRole($request->validated('role'));

        if ($request->validated('role') === 'Student' && $request->validated('birth_year') !== null) {
            $age = (int) date('Y') - (int) $request->validated('birth_year');
            $user->update(['mastery' => max($age - 8, 1) * 150]);
        }

        Auth::login($user);

        return response()->json([
            'message' => 'Registered successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Login a user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => Auth::user(),
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Check if user is authenticated.
     */
    public function check(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => $request->user() !== null,
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the authenticated user's name.
     */
    public function updateName(UpdateUserNameRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['name' => $request->validated('name')]);

        return response()->json([
            'message' => 'Name updated successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Assign a role (Teacher or Student) to a user who has none.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:Teacher,Student'],
        ]);

        $user = $request->user();

        if ($user->hasRole('Teacher') || $user->hasRole('Student')) {
            return response()->json(['message' => 'Role already assigned.'], 422);
        }

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'Role assigned successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
