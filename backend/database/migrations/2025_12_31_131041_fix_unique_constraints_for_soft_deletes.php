<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replace standard unique constraints with partial unique indexes
     * that only apply to non-deleted records. This allows soft-deleted
     * records to have duplicate codes while enforcing uniqueness for active records.
     */
    public function up(): void
    {
        // Work Centers: Drop old unique, create partial unique index
        DB::statement('ALTER TABLE work_centers DROP CONSTRAINT IF EXISTS work_centers_company_id_code_unique');
        DB::statement('CREATE UNIQUE INDEX work_centers_company_id_code_unique ON work_centers (company_id, code) WHERE deleted_at IS NULL');

        // BOMs: Drop old unique, create partial unique index
        DB::statement('ALTER TABLE boms DROP CONSTRAINT IF EXISTS boms_company_id_bom_number_unique');
        DB::statement('CREATE UNIQUE INDEX boms_company_id_bom_number_unique ON boms (company_id, bom_number) WHERE deleted_at IS NULL');

        // Routings: Drop old unique, create partial unique index
        DB::statement('ALTER TABLE routings DROP CONSTRAINT IF EXISTS routings_company_id_routing_number_unique');
        DB::statement('CREATE UNIQUE INDEX routings_company_id_routing_number_unique ON routings (company_id, routing_number) WHERE deleted_at IS NULL');

        // Work Orders: Drop old unique, create partial unique index
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_company_id_work_order_number_unique');
        DB::statement('CREATE UNIQUE INDEX work_orders_company_id_work_order_number_unique ON work_orders (company_id, work_order_number) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Work Centers: Drop partial index, recreate standard unique constraint
        DB::statement('DROP INDEX IF EXISTS work_centers_company_id_code_unique');
        DB::statement('ALTER TABLE work_centers ADD CONSTRAINT work_centers_company_id_code_unique UNIQUE (company_id, code)');

        // BOMs
        DB::statement('DROP INDEX IF EXISTS boms_company_id_bom_number_unique');
        DB::statement('ALTER TABLE boms ADD CONSTRAINT boms_company_id_bom_number_unique UNIQUE (company_id, bom_number)');

        // Routings
        DB::statement('DROP INDEX IF EXISTS routings_company_id_routing_number_unique');
        DB::statement('ALTER TABLE routings ADD CONSTRAINT routings_company_id_routing_number_unique UNIQUE (company_id, routing_number)');

        // Work Orders
        DB::statement('DROP INDEX IF EXISTS work_orders_company_id_work_order_number_unique');
        DB::statement('ALTER TABLE work_orders ADD CONSTRAINT work_orders_company_id_work_order_number_unique UNIQUE (company_id, work_order_number)');
    }
};
