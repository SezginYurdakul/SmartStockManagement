<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users
     * Required permission: users.view
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view users.',
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $users = $this->userService->getUsers($search, $perPage);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user
     * Required permission: users.create
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('users.create')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to create users.',
            ], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = $this->userService->createUser($validated);

        return UserResource::make($user)
            ->additional(['message' => 'User created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user
     * Required permission: users.view
     */
    public function show(Request $request, User $user): JsonResource|JsonResponse
    {
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view users.',
            ], 403);
        }

        $user->load(['roles', 'company']);

        return UserResource::make($user);
    }

    /**
     * Update the specified user
     * Required permission: users.update
     */
    public function update(Request $request, User $user): JsonResource|JsonResponse
    {
        if (!$request->user()->hasPermission('users.edit')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to update users.',
            ], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
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

        $user = $this->userService->updateUser($user, $validated);

        return UserResource::make($user)
            ->additional(['message' => 'User updated successfully']);
    }

    /**
     * Remove the specified user
     * Required permission: users.delete
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to delete users.',
            ], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete yourself.',
            ], 422);
        }

        $this->userService->deleteUser($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Restore a soft deleted user
     * Required permission: users.delete
     */
    public function restore(Request $request, int $id): JsonResource|JsonResponse
    {
        if (!$request->user()->hasPermission('users.delete')) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to restore users.',
            ], 403);
        }

        $user = $this->userService->restoreUser($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user->load(['roles', 'company']);

        return UserResource::make($user)
            ->additional(['message' => 'User restored successfully']);
    }

    /**
     * Permanently delete a user
     * Required role: Admin only
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Forbidden. Only administrators can permanently delete users.',
            ], 403);
        }

        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete yourself.',
            ], 422);
        }

        $this->userService->forceDeleteUser($id);

        return response()->json([
            'message' => 'User permanently deleted',
        ]);
    }
}
