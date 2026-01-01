<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // BOM items: scrap_percentage must be 0-100
        DB::statement('
            ALTER TABLE bom_items
            ADD CONSTRAINT check_bom_items_scrap_percentage
            CHECK (scrap_percentage >= 0 AND scrap_percentage <= 100)
        ');

        // Work centers: efficiency_percentage must be > 0 and <= 100
        DB::statement('
            ALTER TABLE work_centers
            ADD CONSTRAINT check_work_centers_efficiency_percentage
            CHECK (efficiency_percentage > 0 AND efficiency_percentage <= 100)
        ');

        // Work orders: quantities must be non-negative and not exceed ordered
        DB::statement('
            ALTER TABLE work_orders
            ADD CONSTRAINT check_work_orders_quantities
            CHECK (
                quantity_completed >= 0
                AND quantity_scrapped >= 0
                AND (quantity_completed + quantity_scrapped) <= quantity_ordered
            )
        ');

        // Add performance indexes for common queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_boms_product_default
            ON boms(product_id, is_default, status)
            WHERE deleted_at IS NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_work_orders_warehouse_status
            ON work_orders(company_id, warehouse_id, status)
            WHERE deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints
        DB::statement('ALTER TABLE bom_items DROP CONSTRAINT IF EXISTS check_bom_items_scrap_percentage');
        DB::statement('ALTER TABLE work_centers DROP CONSTRAINT IF EXISTS check_work_centers_efficiency_percentage');
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS check_work_orders_quantities');

        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS idx_boms_product_default');
        DB::statement('DROP INDEX IF EXISTS idx_work_orders_warehouse_status');
    }
};
