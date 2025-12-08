<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && !$admin->roles->contains($adminRole->id)) {
            $admin->roles()->attach($adminRole);
        }

        $this->command->info('Admin user created: admin@example.com / password');

        // Create Manager User
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Manager role (if exists)
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole && !$manager->roles->contains($managerRole->id)) {
            $manager->roles()->attach($managerRole);
        }

        $this->command->info('Manager user created: manager@example.com / password');

        // Create Staff User
        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Staff role
        $staffRole = Role::where('name', 'staff')->first();
        if ($staffRole && !$staff->roles->contains($staffRole->id)) {
            $staff->roles()->attach($staffRole);
        }

        $this->command->info('Staff user created: staff@example.com / password');
    }
}
