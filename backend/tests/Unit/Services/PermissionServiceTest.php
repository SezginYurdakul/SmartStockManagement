<?php

namespace Tests\Unit\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionService = new PermissionService();
    }

    /** @test */
    public function it_can_get_paginated_permissions()
    {
        // Create 60 permissions
        for ($i = 1; $i <= 60; $i++) {
            Permission::create([
                'name' => "permission.{$i}",
                'display_name' => "Permission {$i}",
                'module' => 'test',
            ]);
        }

        $result = $this->permissionService->getPermissions();

        $this->assertEquals(50, $result->count()); // Default per page is 50
        $this->assertEquals(60, $result->total());
    }

    /** @test */
    public function it_can_search_permissions_by_name()
    {
        Permission::create(['name' => 'posts.create', 'display_name' => 'Create Posts', 'module' => 'posts']);
        Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts', 'module' => 'posts']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'users']);

        $result = $this->permissionService->getPermissions('posts');

        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function it_can_search_permissions_by_display_name()
    {
        Permission::create(['name' => 'posts.create', 'display_name' => 'Create Posts', 'module' => 'posts']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'users']);

        $result = $this->permissionService->getPermissions('Create');

        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function it_can_filter_permissions_by_module()
    {
        Permission::create(['name' => 'posts.create', 'display_name' => 'Create Posts', 'module' => 'posts']);
        Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts', 'module' => 'posts']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'users']);

        $result = $this->permissionService->getPermissions(null, 'posts');

        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function it_can_create_a_permission()
    {
        $data = [
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
            'description' => 'Ability to create posts',
        ];

        $permission = $this->permissionService->createPermission($data);

        $this->assertInstanceOf(Permission::class, $permission);
        $this->assertEquals('posts.create', $permission->name);
        $this->assertEquals('Create Posts', $permission->display_name);
        $this->assertEquals('posts', $permission->module);
        $this->assertEquals('Ability to create posts', $permission->description);
        $this->assertDatabaseHas('permissions', [
            'name' => 'posts.create',
            'module' => 'posts',
        ]);
    }

    /** @test */
    public function it_can_update_a_permission()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $updateData = [
            'display_name' => 'Create Blog Posts',
            'description' => 'Updated description',
        ];

        $updatedPermission = $this->permissionService->updatePermission($permission, $updateData);

        $this->assertEquals('Create Blog Posts', $updatedPermission->display_name);
        $this->assertEquals('Updated description', $updatedPermission->description);
        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'display_name' => 'Create Blog Posts',
        ]);
    }

    /** @test */
    public function it_can_delete_a_permission()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $result = $this->permissionService->deletePermission($permission);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    /** @test */
    public function it_prevents_deleting_permission_assigned_to_roles()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach($permission->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete permission that is assigned to');

        $this->permissionService->deletePermission($permission);
    }

    /** @test */
    public function it_can_get_all_modules()
    {
        Permission::create(['name' => 'posts.create', 'display_name' => 'Create Posts', 'module' => 'posts']);
        Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts', 'module' => 'posts']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'users']);
        Permission::create(['name' => 'products.create', 'display_name' => 'Create Products', 'module' => 'products']);

        $modules = $this->permissionService->getModules();

        $this->assertIsArray($modules);
        $this->assertCount(3, $modules);
        $this->assertContains('posts', $modules);
        $this->assertContains('users', $modules);
        $this->assertContains('products', $modules);
    }

    /** @test */
    public function it_checks_if_permission_name_exists()
    {
        Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $exists = $this->permissionService->permissionNameExists('posts.create');
        $notExists = $this->permissionService->permissionNameExists('nonexistent.permission');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    /** @test */
    public function it_checks_permission_name_exists_excluding_specific_permission()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $exists = $this->permissionService->permissionNameExists('posts.create', $permission->id);
        $existsOther = $this->permissionService->permissionNameExists('posts.edit', $permission->id);

        $this->assertFalse($exists); // Should not find itself
        $this->assertTrue($existsOther); // Should find other permission
    }

    /** @test */
    public function it_can_get_permission_with_relationships()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach($permission->id);

        $permissionWithRelations = $this->permissionService->getPermissionWithRelations($permission);

        $this->assertTrue($permissionWithRelations->relationLoaded('roles'));
        $this->assertCount(1, $permissionWithRelations->roles);
    }

    /** @test */
    public function it_can_get_permissions_by_module()
    {
        Permission::create(['name' => 'posts.create', 'display_name' => 'Create Posts', 'module' => 'posts']);
        Permission::create(['name' => 'posts.edit', 'display_name' => 'Edit Posts', 'module' => 'posts']);
        Permission::create(['name' => 'posts.delete', 'display_name' => 'Delete Posts', 'module' => 'posts']);
        Permission::create(['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'users']);

        $permissions = $this->permissionService->getPermissionsByModule('posts');

        $this->assertIsArray($permissions);
        $this->assertCount(3, $permissions);
    }

    /** @test */
    public function it_rolls_back_transaction_on_create_failure()
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        // Create permission with duplicate name to cause constraint violation
        Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $data = [
            'name' => 'posts.create',
            'display_name' => 'Create Posts Duplicate',
            'module' => 'posts',
        ];

        try {
            $this->permissionService->createPermission($data);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to create permission', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_update_failure()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        $updateData = [
            'name' => 'posts.edit', // Duplicate name
        ];

        try {
            $this->permissionService->updatePermission($permission, $updateData);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to update permission', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_delete_failure()
    {
        $permission = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        // Permission is assigned to roles, so delete should fail
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $role->permissions()->attach($permission->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete permission that is assigned to');

        $this->permissionService->deletePermission($permission);
    }
}
