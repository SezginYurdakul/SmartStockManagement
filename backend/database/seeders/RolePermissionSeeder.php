<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // User management permissions
            [
                'name' => 'users.view',
                'display_name' => 'View Users',
                'module' => 'users',
                'description' => 'Can view user list and details',
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Create Users',
                'module' => 'users',
                'description' => 'Can create new users',
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Edit Users',
                'module' => 'users',
                'description' => 'Can edit existing users',
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Delete Users',
                'module' => 'users',
                'description' => 'Can delete users',
            ],

            // Role management permissions
            [
                'name' => 'roles.view',
                'display_name' => 'View Roles',
                'module' => 'roles',
                'description' => 'Can view role list and details',
            ],
            [
                'name' => 'roles.create',
                'display_name' => 'Create Roles',
                'module' => 'roles',
                'description' => 'Can create new roles',
            ],
            [
                'name' => 'roles.edit',
                'display_name' => 'Edit Roles',
                'module' => 'roles',
                'description' => 'Can edit existing roles',
            ],
            [
                'name' => 'roles.delete',
                'display_name' => 'Delete Roles',
                'module' => 'roles',
                'description' => 'Can delete roles',
            ],

            // Permission management permissions
            [
                'name' => 'permissions.view',
                'display_name' => 'View Permissions',
                'module' => 'permissions',
                'description' => 'Can view permission list and details',
            ],
            [
                'name' => 'permissions.create',
                'display_name' => 'Create Permissions',
                'module' => 'permissions',
                'description' => 'Can create new permissions',
            ],
            [
                'name' => 'permissions.edit',
                'display_name' => 'Edit Permissions',
                'module' => 'permissions',
                'description' => 'Can edit existing permissions',
            ],
            [
                'name' => 'permissions.delete',
                'display_name' => 'Delete Permissions',
                'module' => 'permissions',
                'description' => 'Can delete permissions',
            ],

            // Product management permissions (for future use)
            [
                'name' => 'products.view',
                'display_name' => 'View Products',
                'module' => 'products',
                'description' => 'Can view product list and details',
            ],
            [
                'name' => 'products.create',
                'display_name' => 'Create Products',
                'module' => 'products',
                'description' => 'Can create new products',
            ],
            [
                'name' => 'products.edit',
                'display_name' => 'Edit Products',
                'module' => 'products',
                'description' => 'Can edit existing products',
            ],
            [
                'name' => 'products.delete',
                'display_name' => 'Delete Products',
                'module' => 'products',
                'description' => 'Can delete products',
            ],

            // Category management permissions
            [
                'name' => 'categories.view',
                'display_name' => 'View Categories',
                'module' => 'categories',
                'description' => 'Can view category list and details',
            ],
            [
                'name' => 'categories.create',
                'display_name' => 'Create Categories',
                'module' => 'categories',
                'description' => 'Can create new categories',
            ],
            [
                'name' => 'categories.edit',
                'display_name' => 'Edit Categories',
                'module' => 'categories',
                'description' => 'Can edit existing categories',
            ],
            [
                'name' => 'categories.delete',
                'display_name' => 'Delete Categories',
                'module' => 'categories',
                'description' => 'Can delete categories',
            ],

            // Inventory management permissions
            [
                'name' => 'inventory.view',
                'display_name' => 'View Inventory',
                'module' => 'inventory',
                'description' => 'Can view inventory levels, warehouses, stock and movements',
            ],
            [
                'name' => 'inventory.create',
                'display_name' => 'Create Inventory',
                'module' => 'inventory',
                'description' => 'Can create warehouses and receive stock',
            ],
            [
                'name' => 'inventory.edit',
                'display_name' => 'Edit Inventory',
                'module' => 'inventory',
                'description' => 'Can edit warehouses, adjust stock, transfer stock',
            ],
            [
                'name' => 'inventory.delete',
                'display_name' => 'Delete Inventory',
                'module' => 'inventory',
                'description' => 'Can delete warehouses',
            ],
            [
                'name' => 'inventory.adjust',
                'display_name' => 'Adjust Inventory',
                'module' => 'inventory',
                'description' => 'Can adjust inventory levels (stock adjustments)',
            ],

            // Settings management permissions
            [
                'name' => 'settings.view',
                'display_name' => 'View Settings',
                'module' => 'settings',
                'description' => 'Can view system settings, currencies, etc.',
            ],
            [
                'name' => 'settings.edit',
                'display_name' => 'Edit Settings',
                'module' => 'settings',
                'description' => 'Can edit system settings, currencies, etc.',
            ],

            // Reports permissions (for future use)
            [
                'name' => 'reports.view',
                'display_name' => 'View Reports',
                'module' => 'reports',
                'description' => 'Can view reports',
            ],
            [
                'name' => 'reports.export',
                'display_name' => 'Export Reports',
                'module' => 'reports',
                'description' => 'Can export reports',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        // Create admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'is_system_role' => true,
            ]
        );

        // Create staff role
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff'],
            [
                'display_name' => 'Staff',
                'description' => 'Limited access for regular staff members',
                'is_system_role' => true,
            ]
        );

        // Assign all permissions to admin
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));

        // Assign limited permissions to staff (only view permissions)
        $staffPermissions = Permission::whereIn('name', [
            'users.view',
            'products.view',
            'categories.view',
            'inventory.view',
            'reports.view',
        ])->get();
        $staffRole->permissions()->sync($staffPermissions->pluck('id'));
    }
}
