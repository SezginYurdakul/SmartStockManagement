<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');
        $module = $request->input('module');

        $query = Permission::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if ($module) {
            $query->where('module', $module);
        }

        $permissions = $query->orderBy('module')->orderBy('name')->paginate($perPage);

        return response()->json($permissions);
    }

    /**
     * Store a newly created permission
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions|regex:/^[a-z0-9.-]+$/',
            'display_name' => 'required|string|max:255',
            'module' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($validated);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'permission' => $permission->load('roles'),
        ]);
    }

    /**
     * Update the specified permission
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9.-]+$/',
                Rule::unique('permissions')->ignore($permission->id),
            ],
            'display_name' => 'sometimes|required|string|max:255',
            'module' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $permission->update($validated);

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $permission->fresh(),
        ]);
    }

    /**
     * Remove the specified permission
     */
    public function destroy(Permission $permission): JsonResponse
    {
        // Check if permission is assigned to any roles
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete permission that is assigned to roles',
                'roles_count' => $permission->roles()->count(),
            ], 409);
        }

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Get all available modules
     */
    public function modules(): JsonResponse
    {
        $modules = Permission::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return response()->json([
            'modules' => $modules,
        ]);
    }
}