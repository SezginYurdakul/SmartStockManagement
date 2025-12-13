<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolePermissionSeeder::class);

        // Seed test users
        $this->call(UserSeeder::class);

        // Seed categories
        $this->call(CategorySeeder::class);

        // Seed attributes and values
        $this->call(AttributeSeeder::class);

        // Seed products (depends on categories)
        $this->call(ProductSeeder::class);

        // Assign attributes to categories
        $this->call(CategoryAttributeSeeder::class);

        // Assign attributes to products (Brand, Warranty, Material)
        $this->call(ProductAttributeSeeder::class);

        // Generate product variants (Color, Size, Storage combinations)
        $this->call(ProductVariantSeeder::class);

        // Uncomment to create additional random users
        // User::factory(10)->create();
    }
}
