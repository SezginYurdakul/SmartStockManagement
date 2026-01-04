<?php

namespace Database\Seeders;

use Database\Seeders\Traits\SeederModeTrait;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    use SeederModeTrait;

    /**
     * Seed the application's database.
     *
     * Usage:
     *   php artisan db:seed                    # Minimal mode (system essentials only)
     *   php artisan db:seed --demo             # Demo mode (with sample data)
     *   php artisan migrate:fresh --seed       # Minimal mode
     *   php artisan migrate:fresh --seed --demo # Demo mode
     *
     * Or set environment variable:
     *   SEED_MODE=demo php artisan db:seed
     */
    public function run(): void
    {
        $this->outputModeInfo();

        // ═══════════════════════════════════════════════════════════════
        // CORE SYSTEM (Always Required)
        // These seeders run in both minimal and demo modes
        // ═══════════════════════════════════════════════════════════════

        // Company (required for multi-tenant architecture)
        $this->call(CompanySeeder::class);

        // System settings (app configuration)
        $this->call(SettingsSeeder::class);

        // Roles and permissions (RBAC)
        $this->call(RolePermissionSeeder::class);

        // Admin user (at minimum, need one admin to login)
        $this->call(UserSeeder::class);

        // Currencies (base currency required)
        $this->call(CurrencySeeder::class);

        // Product types (Simple, Configurable, etc.)
        $this->call(ProductTypeSeeder::class);

        // Units of measure (kg, pcs, m, etc.)
        $this->call(UnitOfMeasureSeeder::class);

        // ═══════════════════════════════════════════════════════════════
        // DEMO DATA (Optional - only in demo mode)
        // Sample data for testing and demonstration
        // ═══════════════════════════════════════════════════════════════

        if ($this->isDemoMode()) {
            $this->command->info('Seeding demo data...');

            // Categories (sample category hierarchy)
            $this->call(CategorySeeder::class);

            // Attributes and values (Color, Size, Material, etc.)
            $this->call(AttributeSeeder::class);

            // Products (sample products)
            $this->call(ProductSeeder::class);

            // Product-specific UOM conversions
            $this->call(ProductUomConversionSeeder::class);

            // Category-attribute assignments
            $this->call(CategoryAttributeSeeder::class);

            // Product-attribute assignments
            $this->call(ProductAttributeSeeder::class);

            // Product variants (Color/Size combinations)
            $this->call(ProductVariantSeeder::class);

            // Warehouses (sample locations)
            $this->call(WarehouseSeeder::class);

            // Stock and movements (sample inventory)
            $this->call(StockSeeder::class);

            // Suppliers (sample vendors)
            $this->call(SupplierSeeder::class);

            // QC data (acceptance rules, inspections, NCRs)
            $this->call(QualityControlSeeder::class);

            // Manufacturing (work centers, BOMs, routings, work orders)
            $this->call(ManufacturingSeeder::class);

            // Sales (customer groups, customers, orders, deliveries)
            $this->call(SalesSeeder::class);

            $this->command->info('Demo data seeding completed!');
        } else {
            $this->command->info('Minimal mode: Skipping demo data.');
            $this->command->info('To include demo data, run: php artisan db:seed --demo');
        }
    }
}
