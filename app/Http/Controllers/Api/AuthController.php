<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->unauthorized('User not authenticated');
            }

            return $this->success([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin ?? false,
            ], 'User retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while retrieving user', $e);
        }
    }

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $token = $user->createToken('auth-token')->plainTextToken;

                return $this->success([
                    'user' => $user,
                    'token' => $token,
                ], 'Login successful');
            }

            return $this->unauthorized('Invalid credentials');
        } catch (\Exception $e) {
            return $this->serverError('An error occurred during login', $e);
        }
    }

    /**
     * Register user.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = \App\Models\User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->created([
                'user' => $user,
                'token' => $token,
            ], 'Registration successful');
        } catch (\Exception $e) {
            return $this->serverError('An error occurred during registration', $e);
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return $this->success(null, 'Logout successful');
        } catch (\Exception $e) {
            return $this->serverError('An error occurred during logout', $e);
        }
    }
}
