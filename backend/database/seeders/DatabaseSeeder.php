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
        // Seed company first (required for multi-tenant data)
        $this->call(CompanySeeder::class);

        // Seed system settings
        $this->call(SettingsSeeder::class);

        // Seed roles and permissions
        $this->call(RolePermissionSeeder::class);

        // Seed test users (with company assignment)
        $this->call(UserSeeder::class);

        // Seed currencies and exchange rates
        $this->call(CurrencySeeder::class);

        // Seed product types
        $this->call(ProductTypeSeeder::class);

        // Seed units of measure
        $this->call(UnitOfMeasureSeeder::class);

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

        // Seed warehouses
        $this->call(WarehouseSeeder::class);

        // Seed stock and stock movements
        $this->call(StockSeeder::class);

        // Seed suppliers (Phase 3 - Procurement)
        $this->call(SupplierSeeder::class);

        // Seed QC test scenarios (acceptance rules, inspections, NCRs)
        $this->call(QualityControlSeeder::class);

        // Seed manufacturing data (work centers, BOMs, routings)
        $this->call(ManufacturingSeeder::class);
    }
}
