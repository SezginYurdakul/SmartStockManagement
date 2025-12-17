<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class UserService
{
    /**
     * Get paginated users with optional search
     */
    public function getUsers(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        Log::info('Creating new user', [
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Assign roles if provided
            if (isset($data['role_ids'])) {
                $this->assignRoles($user, $data['role_ids']);
                Log::debug('Assigned roles to user', [
                    'user_id' => $user->id,
                    'role_ids' => $data['role_ids'],
                ]);
            }

            DB::commit();

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user->fresh(['roles']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create user', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Failed to create user: {$e->getMessage()}");
        }
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        Log::info('Updating user', [
            'user_id' => $user->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $updateData = [
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
            ];

            // Update password if provided
            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
                Log::debug('Password updated for user', ['user_id' => $user->id]);
            }

            $user->update($updateData);

            // Update roles if provided
            if (isset($data['role_ids'])) {
                $this->syncRoles($user, $data['role_ids']);
                Log::debug('Roles synced for user', [
                    'user_id' => $user->id,
                    'role_ids' => $data['role_ids'],
                ]);
            }

            DB::commit();

            Log::info('User updated successfully', [
                'user_id' => $user->id,
            ]);

            return $user->fresh(['roles']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to update user: {$e->getMessage()}");
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser(User $user): bool
    {
        Log::info('Deleting user', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        try {
            $result = $user->delete();

            Log::info('User deleted successfully', [
                'user_id' => $user->id,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete user: {$e->getMessage()}");
        }
    }

    /**
     * Restore soft deleted user
     */
    public function restoreUser(int $userId): ?User
    {
        Log::info('Restoring user', ['user_id' => $userId]);

        try {
            $user = User::withTrashed()->find($userId);

            if ($user) {
                $user->restore();

                Log::info('User restored successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return $user;
            }

            Log::warning('User not found for restoration', ['user_id' => $userId]);
            return null;

        } catch (Exception $e) {
            Log::error('Failed to restore user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to restore user: {$e->getMessage()}");
        }
    }

    /**
     * Permanently delete user
     */
    public function forceDeleteUser(int $userId): bool
    {
        Log::warning('Force deleting user permanently', ['user_id' => $userId]);

        try {
            $user = User::withTrashed()->find($userId);

            if ($user) {
                $email = $user->email;
                $result = $user->forceDelete();

                Log::warning('User permanently deleted', [
                    'user_id' => $userId,
                    'email' => $email,
                ]);

                return $result;
            }

            Log::warning('User not found for permanent deletion', ['user_id' => $userId]);
            return false;

        } catch (Exception $e) {
            Log::error('Failed to permanently delete user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to permanently delete user: {$e->getMessage()}");
        }
    }

    /**
     * Assign roles to user (attach)
     */
    public function assignRoles(User $user, array $roleIds): void
    {
        $user->roles()->attach($roleIds);
    }

    /**
     * Sync roles for user (replace existing)
     */
    public function syncRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
    }

    /**
     * Remove roles from user
     */
    public function removeRoles(User $user, array $roleIds): void
    {
        $user->roles()->detach($roleIds);
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $query = User::where('email', $email);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }
}
