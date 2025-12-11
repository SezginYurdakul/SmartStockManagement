<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class RoleService
{
    /**
     * Get paginated roles with optional search
     */
    public function getRoles(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::with('permissions');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new role
     */
    public function createRole(array $data): Role
    {
        Log::info('Creating new role', [
            'name' => $data['name'],
            'display_name' => $data['display_name'],
        ]);

        DB::beginTransaction();

        try {
            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system_role' => false,
            ]);

            // Assign permissions if provided
            if (isset($data['permission_ids']) && !empty($data['permission_ids'])) {
                $role->permissions()->attach($data['permission_ids']);
                Log::debug('Assigned permissions to role', [
                    'role_id' => $role->id,
                    'permission_count' => count($data['permission_ids']),
                ]);
            }

            DB::commit();

            Log::info('Role created successfully', [
                'role_id' => $role->id,
                'name' => $role->name,
            ]);

            return $role->fresh(['permissions']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create role', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Failed to create role: {$e->getMessage()}");
        }
    }

    /**
     * Update role
     */
    public function updateRole(Role $role, array $data): Role
    {
        Log::info('Updating role', [
            'role_id' => $role->id,
            'changes' => array_keys($data),
        ]);

        // Check if trying to update system role name
        if ($role->is_system_role && isset($data['name'])) {
            Log::warning('Attempted to change system role name', [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);

            throw new Exception('Cannot change system role name');
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $data['name'] ?? $role->name,
                'display_name' => $data['display_name'] ?? $role->display_name,
                'description' => $data['description'] ?? $role->description,
            ];

            $role->update($updateData);

            // Update permissions if provided
            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
                Log::debug('Permissions synced for role', [
                    'role_id' => $role->id,
                    'permission_count' => count($data['permission_ids']),
                ]);
            }

            DB::commit();

            Log::info('Role updated successfully', [
                'role_id' => $role->id,
            ]);

            return $role->fresh(['permissions']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to update role: {$e->getMessage()}");
        }
    }

    /**
     * Delete role
     */
    public function deleteRole(Role $role): bool
    {
        Log::info('Deleting role', [
            'role_id' => $role->id,
            'name' => $role->name,
        ]);

        // Prevent deleting system roles
        if ($role->is_system_role) {
            Log::warning('Attempted to delete system role', [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);

            throw new Exception('Cannot delete system role');
        }

        // Check if role is assigned to any users
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            Log::warning('Attempted to delete role assigned to users', [
                'role_id' => $role->id,
                'users_count' => $usersCount,
            ]);

            throw new Exception("Cannot delete role that is assigned to {$usersCount} user(s)");
        }

        try {
            $result = $role->delete();

            Log::info('Role deleted successfully', [
                'role_id' => $role->id,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete role: {$e->getMessage()}");
        }
    }

    /**
     * Assign permissions to role (add without removing existing)
     */
    public function assignPermissions(Role $role, array $permissionIds): void
    {
        Log::info('Assigning permissions to role', [
            'role_id' => $role->id,
            'permission_count' => count($permissionIds),
        ]);

        try {
            $role->permissions()->syncWithoutDetaching($permissionIds);

            Log::info('Permissions assigned successfully', [
                'role_id' => $role->id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to assign permissions', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to assign permissions: {$e->getMessage()}");
        }
    }

    /**
     * Revoke permissions from role
     */
    public function revokePermissions(Role $role, array $permissionIds): void
    {
        Log::info('Revoking permissions from role', [
            'role_id' => $role->id,
            'permission_count' => count($permissionIds),
        ]);

        try {
            $role->permissions()->detach($permissionIds);

            Log::info('Permissions revoked successfully', [
                'role_id' => $role->id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to revoke permissions', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to revoke permissions: {$e->getMessage()}");
        }
    }

    /**
     * Sync permissions for role (replace all existing)
     */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        Log::info('Syncing permissions for role', [
            'role_id' => $role->id,
            'permission_count' => count($permissionIds),
        ]);

        try {
            $role->permissions()->sync($permissionIds);

            Log::info('Permissions synced successfully', [
                'role_id' => $role->id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to sync permissions', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to sync permissions: {$e->getMessage()}");
        }
    }

    /**
     * Check if role name exists
     */
    public function roleNameExists(string $name, ?int $excludeRoleId = null): bool
    {
        $query = Role::where('name', $name);

        if ($excludeRoleId) {
            $query->where('id', '!=', $excludeRoleId);
        }

        return $query->exists();
    }

    /**
     * Get role with all relationships
     */
    public function getRoleWithRelations(Role $role): Role
    {
        return $role->load(['permissions', 'users']);
    }
}
