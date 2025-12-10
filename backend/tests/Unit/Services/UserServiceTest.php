<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }

    /** @test */
    public function it_can_get_paginated_users()
    {
        User::factory()->count(25)->create();

        $result = $this->userService->getUsers();

        $this->assertEquals(15, $result->count());
        $this->assertEquals(25, $result->total());
    }

    /** @test */
    public function it_can_search_users_by_name()
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@example.com']);

        $result = $this->userService->getUsers('John');

        $this->assertEquals(2, $result->total()); // John Doe and Bob Johnson
    }

    /** @test */
    public function it_can_search_users_by_email()
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@test.com']);

        $result = $this->userService->getUsers('example.com');

        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function it_can_create_a_user_without_roles()
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->userService->createUser($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function it_can_create_a_user_with_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role2 = Role::create(['name' => 'manager', 'display_name' => 'Manager']);

        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role_ids' => [$role1->id, $role2->id],
        ];

        $user = $this->userService->createUser($data);

        $this->assertCount(2, $user->roles);
        $this->assertTrue($user->roles->contains($role1));
        $this->assertTrue($user->roles->contains($role2));
    }

    /** @test */
    public function it_can_update_user_basic_info()
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $updatedUser = $this->userService->updateUser($user, $updateData);

        $this->assertEquals('Updated Name', $updatedUser->name);
        $this->assertEquals('updated@example.com', $updatedUser->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /** @test */
    public function it_can_update_user_password()
    {
        $user = User::factory()->create();
        $oldPassword = $user->password;

        $updateData = [
            'password' => 'newpassword123',
        ];

        $updatedUser = $this->userService->updateUser($user, $updateData);

        $this->assertNotEquals($oldPassword, $updatedUser->password);
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
    }

    /** @test */
    public function it_can_update_user_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role2 = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $role3 = Role::create(['name' => 'user', 'display_name' => 'User']);

        $user = User::factory()->create();
        $user->roles()->attach([$role1->id, $role2->id]);

        $updateData = [
            'role_ids' => [$role3->id],
        ];

        $updatedUser = $this->userService->updateUser($user, $updateData);

        $this->assertCount(1, $updatedUser->roles);
        $this->assertTrue($updatedUser->roles->contains($role3));
        $this->assertFalse($updatedUser->roles->contains($role1));
    }

    /** @test */
    public function it_can_delete_a_user()
    {
        $user = User::factory()->create();

        $result = $this->userService->deleteUser($user);

        $this->assertTrue($result);
        // User model doesn't use SoftDeletes, so it's hard deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function it_can_assign_roles_to_user()
    {
        $role1 = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role2 = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $user = User::factory()->create();

        $this->userService->assignRoles($user, [$role1->id, $role2->id]);

        $this->assertCount(2, $user->fresh()->roles);
    }

    /** @test */
    public function it_can_sync_roles_for_user()
    {
        $role1 = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role2 = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $role3 = Role::create(['name' => 'user', 'display_name' => 'User']);

        $user = User::factory()->create();
        $user->roles()->attach([$role1->id, $role2->id]);

        $this->userService->syncRoles($user, [$role3->id]);

        $freshUser = $user->fresh();
        $this->assertCount(1, $freshUser->roles);
        $this->assertTrue($freshUser->roles->contains($role3));
    }

    /** @test */
    public function it_can_remove_roles_from_user()
    {
        $role1 = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $role2 = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $user = User::factory()->create();
        $user->roles()->attach([$role1->id, $role2->id]);

        $this->userService->removeRoles($user, [$role1->id]);

        $freshUser = $user->fresh();
        $this->assertCount(1, $freshUser->roles);
        $this->assertFalse($freshUser->roles->contains($role1));
        $this->assertTrue($freshUser->roles->contains($role2));
    }

    /** @test */
    public function it_checks_if_email_exists()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $exists = $this->userService->emailExists('existing@example.com');
        $notExists = $this->userService->emailExists('nonexistent@example.com');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    /** @test */
    public function it_checks_email_exists_excluding_specific_user()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $exists = $this->userService->emailExists('test@example.com', $user->id);
        $existsOther = $this->userService->emailExists('other@example.com', $user->id);

        $this->assertFalse($exists); // Should not find itself
        $this->assertTrue($existsOther); // Should find other user
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

        // Create a user with duplicate email to cause constraint violation
        User::factory()->create(['email' => 'test@example.com']);

        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        try {
            $this->userService->createUser($data);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to create user', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_update_failure()
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        User::factory()->create(['email' => 'existing@example.com']);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        $updateData = [
            'email' => 'existing@example.com', // Duplicate email
        ];

        try {
            $this->userService->updateUser($user, $updateData);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to update user', $e->getMessage());
            throw $e;
        }
    }
}
