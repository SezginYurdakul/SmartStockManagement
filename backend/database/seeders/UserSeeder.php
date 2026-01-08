<?php

namespace Database\Seeders;

use App\Models\Company;
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
        // Get all companies
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->error('No companies found! Please run CompanySeeder first.');
            return;
        }

        // Get roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $staffRole = Role::where('name', 'staff')->first();

        // Create users for each company
        foreach ($companies as $index => $company) {
            $companyNumber = $index + 1;

            // Create Admin User for this company
            $admin = User::firstOrCreate(
                ['email' => "admin{$companyNumber}@example.com"],
                [
                    'company_id' => $company->id,
                    'first_name' => 'Admin',
                    'last_name' => $company->name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign Admin role
            if ($adminRole && !$admin->roles->contains($adminRole->id)) {
                $admin->roles()->attach($adminRole);
            }

            $this->command->info("Admin user created for {$company->name}: admin{$companyNumber}@example.com / password");

            // Create Manager User for this company
            if ($managerRole) {
                $manager = User::firstOrCreate(
                    ['email' => "manager{$companyNumber}@example.com"],
                    [
                        'company_id' => $company->id,
                        'first_name' => 'Manager',
                        'last_name' => $company->name,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (!$manager->roles->contains($managerRole->id)) {
                    $manager->roles()->attach($managerRole);
                }

                $this->command->info("Manager user created for {$company->name}: manager{$companyNumber}@example.com / password");
            }

            // Create Staff User for this company
            if ($staffRole) {
                $staff = User::firstOrCreate(
                    ['email' => "staff{$companyNumber}@example.com"],
                    [
                        'company_id' => $company->id,
                        'first_name' => 'Staff',
                        'last_name' => $company->name,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (!$staff->roles->contains($staffRole->id)) {
                    $staff->roles()->attach($staffRole);
                }

                $this->command->info("Staff user created for {$company->name}: staff{$companyNumber}@example.com / password");
            }
        }

        // Keep backward compatibility: Create users with original emails for first company
        $firstCompany = $companies->first();
        if ($firstCompany) {
            // Admin User (original email)
            $admin = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'company_id' => $firstCompany->id,
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            if ($adminRole && !$admin->roles->contains($adminRole->id)) {
                $admin->roles()->attach($adminRole);
            }

            // Manager User (original email)
            if ($managerRole) {
                $manager = User::firstOrCreate(
                    ['email' => 'manager@example.com'],
                    [
                        'company_id' => $firstCompany->id,
                        'first_name' => 'Manager',
                        'last_name' => 'User',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (!$manager->roles->contains($managerRole->id)) {
                    $manager->roles()->attach($managerRole);
                }
            }

            // Staff User (original email)
            if ($staffRole) {
                $staff = User::firstOrCreate(
                    ['email' => 'staff@example.com'],
                    [
                        'company_id' => $firstCompany->id,
                        'first_name' => 'Staff',
                        'last_name' => 'User',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (!$staff->roles->contains($staffRole->id)) {
                    $staff->roles()->attach($staffRole);
                }
            }
        }

        // Create Platform Admin User
        // Platform admin has company_id = null (not tied to any company)
        // This allows them to see and manage all companies
        $platformAdmin = User::firstOrCreate(
            ['email' => 'platform@example.com'],
            [
                'company_id' => null, // Platform admin is not tied to any company
                'first_name' => 'Platform',
                'last_name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Assign Platform Admin role
        $platformAdminRole = Role::where('name', 'platform_admin')->first();
        if ($platformAdminRole && !$platformAdmin->roles->contains($platformAdminRole->id)) {
            $platformAdmin->roles()->attach($platformAdminRole);
        }

        $this->command->info('Platform Admin user created: platform@example.com / password');
        $this->command->warn('⚠️  Platform Admin has access to ALL companies. Use with caution!');
    }
}
