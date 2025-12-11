<?php

namespace Tests\Unit\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = new RoleService();
    }

    /** @test */
    public function it_can_get_paginated_roles()
    {
        // Create 25 roles manually
        for ($i = 1; $i <= 25; $i++) {
            Role::create([
                'name' => "role-{$i}",
                'display_name' => "Role {$i}",
            ]);
        }

        $result = $this->roleService->getRoles();

        $this->assertEquals(15, $result->count());
        $this->assertEquals(25, $result->total());
    }

    /** @test */
    public function it_can_search_roles_by_name()
    {
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        Role::create(['name' => 'user', 'display_name' => 'User']);

        $result = $this->roleService->getRoles('admin');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('admin', $result->first()->name);
    }

    /** @test */
    public function it_can_search_roles_by_display_name()
    {
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::create(['name' => 'manager', 'display_name' => 'Manager']);

        $result = $this->roleService->getRoles('Manager');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('manager', $result->first()->name);
    }

    /** @test */
    public function it_can_create_a_role_without_permissions()
    {
        $data = [
            'name' => 'editor',
            'display_name' => 'Editor',
            'description' => 'Content editor role',
        ];

        $role = $this->roleService->createRole($data);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('editor', $role->name);
        $this->assertEquals('Editor', $role->display_name);
        $this->assertEquals('Content editor role', $role->description);
        $this->assertFalse($role->is_system_role);
        $this->assertDatabaseHas('roles', [
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);
    }

    /** @test */
    public function it_can_create_a_role_with_permissions()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $data = [
            'name' => 'editor',
            'display_name' => 'Editor',
            'permission_ids' => [$permission1->id, $permission2->id],
        ];

        $role = $this->roleService->createRole($data);

        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains($permission1));
        $this->assertTrue($role->permissions->contains($permission2));
    }

    /** @test */
    public function it_can_update_role_basic_info()
    {
        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $updateData = [
            'display_name' => 'Content Editor',
            'description' => 'Manages content',
        ];

        $updatedRole = $this->roleService->updateRole($role, $updateData);

        $this->assertEquals('Content Editor', $updatedRole->display_name);
        $this->assertEquals('Manages content', $updatedRole->description);
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'display_name' => 'Content Editor',
        ]);
    }

    /** @test */
    public function it_can_update_role_permissions()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $permission3 = Permission::create([
            'name' => 'posts.delete',
            'display_name' => 'Delete Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $updateData = [
            'permission_ids' => [$permission3->id],
        ];

        $updatedRole = $this->roleService->updateRole($role, $updateData);

        $this->assertCount(1, $updatedRole->permissions);
        $this->assertTrue($updatedRole->permissions->contains($permission3));
        $this->assertFalse($updatedRole->permissions->contains($permission1));
    }

    /** @test */
    public function it_prevents_updating_system_role_name()
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system_role' => true,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot change system role name');

        $this->roleService->updateRole($role, ['name' => 'super-admin']);
    }

    /** @test */
    public function it_can_delete_a_role()
    {
        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $result = $this->roleService->deleteRole($role);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    /** @test */
    public function it_prevents_deleting_system_role()
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system_role' => true,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete system role');

        $this->roleService->deleteRole($role);
    }

    /** @test */
    public function it_prevents_deleting_role_assigned_to_users()
    {
        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot delete role that is assigned to');

        $this->roleService->deleteRole($role);
    }

    /** @test */
    public function it_can_assign_permissions_to_role()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $this->roleService->assignPermissions($role, [$permission1->id, $permission2->id]);

        $this->assertCount(2, $role->fresh()->permissions);
    }

    /** @test */
    public function it_can_assign_permissions_without_removing_existing()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach($permission1->id);

        $this->roleService->assignPermissions($role, [$permission2->id]);

        $freshRole = $role->fresh();
        $this->assertCount(2, $freshRole->permissions);
        $this->assertTrue($freshRole->permissions->contains($permission1));
        $this->assertTrue($freshRole->permissions->contains($permission2));
    }

    /** @test */
    public function it_can_revoke_permissions_from_role()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $this->roleService->revokePermissions($role, [$permission1->id]);

        $freshRole = $role->fresh();
        $this->assertCount(1, $freshRole->permissions);
        $this->assertFalse($freshRole->permissions->contains($permission1));
        $this->assertTrue($freshRole->permissions->contains($permission2));
    }

    /** @test */
    public function it_can_sync_permissions_for_role()
    {
        $permission1 = Permission::create([
            'name' => 'posts.create',
            'display_name' => 'Create Posts',
            'module' => 'posts',
        ]);

        $permission2 = Permission::create([
            'name' => 'posts.edit',
            'display_name' => 'Edit Posts',
            'module' => 'posts',
        ]);

        $permission3 = Permission::create([
            'name' => 'posts.delete',
            'display_name' => 'Delete Posts',
            'module' => 'posts',
        ]);

        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $this->roleService->syncPermissions($role, [$permission3->id]);

        $freshRole = $role->fresh();
        $this->assertCount(1, $freshRole->permissions);
        $this->assertTrue($freshRole->permissions->contains($permission3));
        $this->assertFalse($freshRole->permissions->contains($permission1));
    }

    /** @test */
    public function it_checks_if_role_name_exists()
    {
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);

        $exists = $this->roleService->roleNameExists('admin');
        $notExists = $this->roleService->roleNameExists('nonexistent');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    /** @test */
    public function it_checks_role_name_exists_excluding_specific_role()
    {
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);

        $exists = $this->roleService->roleNameExists('editor', $role->id);
        $existsOther = $this->roleService->roleNameExists('admin', $role->id);

        $this->assertFalse($exists); // Should not find itself
        $this->assertTrue($existsOther); // Should find other role
    }

    /** @test */
    public function it_can_get_role_with_relationships()
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

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $roleWithRelations = $this->roleService->getRoleWithRelations($role);

        $this->assertTrue($roleWithRelations->relationLoaded('permissions'));
        $this->assertTrue($roleWithRelations->relationLoaded('users'));
        $this->assertCount(1, $roleWithRelations->permissions);
        $this->assertCount(1, $roleWithRelations->users);
    }

    /** @test */
    public function it_rolls_back_transaction_on_create_failure()
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        // Create role with duplicate name to cause constraint violation
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);

        $data = [
            'name' => 'admin',
            'display_name' => 'Admin Duplicate',
        ];

        try {
            $this->roleService->createRole($data);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to create role', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_update_failure()
    {
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        $updateData = [
            'name' => 'admin', // Duplicate name
        ];

        try {
            $this->roleService->updateRole($role, $updateData);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to update role', $e->getMessage());
            throw $e;
        }
    }
}
