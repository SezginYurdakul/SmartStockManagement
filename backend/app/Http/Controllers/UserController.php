<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users
     * Required permission: users.view
     */
    public function index(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view users.',
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Store a newly created user
     * Required permission: users.create
     */
    public function store(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.create')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to create users.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Assign roles if provided
        if (isset($validated['role_ids'])) {
            $user->roles()->attach($validated['role_ids']);
        }

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('roles'),
        ], 201);
    }

    /**
     * Display the specified user
     * Required permission: users.view
     */
    public function show(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view users.',
            ], 403);
        }

        return response()->json([
            'user' => $user->load('roles.permissions'),
        ]);
    }

    /**
     * Update the specified user
     * Required permission: users.update
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.update')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to update users.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|required|string|min:8|confirmed',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        // Update roles if provided
        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()->load('roles'),
        ]);
    }

    /**
     * Remove the specified user
     * Required permission: users.delete
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to delete users.',
            ], 403);
        }

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete yourself.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Restore a soft deleted user
     * Required permission: users.delete
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        // Check permission
        if (!$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to restore users.',
            ], 403);
        }

        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'message' => 'User restored successfully',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Permanently delete a user
     * Required role: Admin only
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        // Only admins can permanently delete
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Forbidden. Only administrators can permanently delete users.',
            ], 403);
        }

        $user = User::withTrashed()->findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete yourself.',
            ], 422);
        }

        $user->forceDelete();

        return response()->json([
            'message' => 'User permanently deleted',
        ]);
    }
}
