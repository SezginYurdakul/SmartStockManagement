<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * NOTE: For SaaS applications, public registration is typically disabled.
     * Users should be created by company administrators via UserController.
     * This endpoint may be kept for initial company setup or removed entirely.
     * 
     * If kept, it requires company_id to be provided (for initial setup only).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Validate company is active
        $company = \App\Models\Company::findOrFail($validated['company_id']);
        if (!$company->is_active) {
            throw ValidationException::withMessages([
                'company_id' => ['The selected company is not active.'],
            ]);
        }

        // Use forLogin scope to bypass company filter for creation
        $user = User::forLogin()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $validated['company_id'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Use forLogin scope to bypass company filter (email is unique globally)
        $user = User::forLogin()->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Validate company (skip for platform admins)
        if ($user->company_id === null) {
            // Platform admin - no company validation needed
            if (!$user->hasRole('platform_admin')) {
                throw ValidationException::withMessages([
                    'email' => ['User does not belong to a company. Please contact support.'],
                ]);
            }
        } else {
            // Regular user - validate company
            $company = $user->company;
            if (!$company || !$company->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Company account is not active. Please contact support.'],
                ]);
            }
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['User account is inactive. Please contact administrator.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'user' => null,
            ], 401);
        }
        
        // Load relationships
        $user->load(['company', 'roles']);
        
        return response()->json([
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user exists
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // For security, don't reveal if user exists
            return response()->json([
                'message' => 'If your email exists in our system, you will receive a password reset link.',
            ]);
        }

        // Delete old tokens
        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        // Create new token
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $validated['email'],
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // TODO: Send email with reset link
        // For now, return token in response (development only)
        // In production, this should be sent via email

        return response()->json([
            'message' => 'If your email exists in our system, you will receive a password reset link.',
            'token' => $token, // REMOVE THIS IN PRODUCTION
            'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . $validated['email'], // Development only
        ]);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Find token record
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Verify token
        if (!Hash::check($validated['token'], $passwordReset->token)) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        // Check if token is expired (1 hour)
        if (now()->diffInMinutes($passwordReset->created_at) > 60) {
            DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->delete();

            return response()->json([
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Update user password
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Delete token
        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->delete();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);
    }
}
