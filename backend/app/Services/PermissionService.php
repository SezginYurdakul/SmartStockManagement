<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class PermissionService
{
    /**
     * Get paginated permissions with optional filters
     */
    public function getPermissions(?string $search = null, ?string $module = null, int $perPage = 50): LengthAwarePaginator
    {
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

        return $query->orderBy('module')->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new permission
     */
    public function createPermission(array $data): Permission
    {
        Log::info('Creating new permission', [
            'name' => $data['name'],
            'module' => $data['module'],
        ]);

        DB::beginTransaction();

        try {
            $permission = Permission::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'module' => $data['module'],
                'description' => $data['description'] ?? null,
            ]);

            DB::commit();

            Log::info('Permission created successfully', [
                'permission_id' => $permission->id,
                'name' => $permission->name,
            ]);

            return $permission;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create permission', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Failed to create permission: {$e->getMessage()}");
        }
    }

    /**
     * Update permission
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        Log::info('Updating permission', [
            'permission_id' => $permission->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $data['name'] ?? $permission->name,
                'display_name' => $data['display_name'] ?? $permission->display_name,
                'module' => $data['module'] ?? $permission->module,
                'description' => $data['description'] ?? $permission->description,
            ];

            $permission->update($updateData);

            DB::commit();

            Log::info('Permission updated successfully', [
                'permission_id' => $permission->id,
            ]);

            return $permission->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update permission', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to update permission: {$e->getMessage()}");
        }
    }

    /**
     * Delete permission
     */
    public function deletePermission(Permission $permission): bool
    {
        Log::info('Deleting permission', [
            'permission_id' => $permission->id,
            'name' => $permission->name,
        ]);

        // Check if permission is assigned to any roles
        $rolesCount = $permission->roles()->count();
        if ($rolesCount > 0) {
            Log::warning('Attempted to delete permission assigned to roles', [
                'permission_id' => $permission->id,
                'roles_count' => $rolesCount,
            ]);

            throw new Exception("Cannot delete permission that is assigned to {$rolesCount} role(s)");
        }

        DB::beginTransaction();

        try {
            $result = $permission->delete();

            DB::commit();

            Log::info('Permission deleted successfully', [
                'permission_id' => $permission->id,
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete permission', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete permission: {$e->getMessage()}");
        }
    }

    /**
     * Get all available modules
     */
    public function getModules(): array
    {
        return Permission::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->toArray();
    }

    /**
     * Check if permission name exists
     */
    public function permissionNameExists(string $name, ?int $excludePermissionId = null): bool
    {
        $query = Permission::where('name', $name);

        if ($excludePermissionId) {
            $query->where('id', '!=', $excludePermissionId);
        }

        return $query->exists();
    }

    /**
     * Get permission with all relationships
     */
    public function getPermissionWithRelations(Permission $permission): Permission
    {
        return $permission->load('roles');
    }

    /**
     * Get permissions by module
     */
    public function getPermissionsByModule(string $module): array
    {
        return Permission::where('module', $module)
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
