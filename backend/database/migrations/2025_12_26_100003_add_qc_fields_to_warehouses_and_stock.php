<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update warehouse_type enum to include QC-related types
        // PostgreSQL requires dropping and recreating the enum
        DB::statement("ALTER TABLE warehouses ALTER COLUMN warehouse_type TYPE VARCHAR(50)");

        // Add new columns to warehouses
        Schema::table('warehouses', function (Blueprint $table) {
            // QC-related settings
            $table->boolean('is_quarantine_zone')->default(false)->after('is_default');
            $table->boolean('is_rejection_zone')->default(false)->after('is_quarantine_zone');
            $table->boolean('requires_qc_release')->default(false)->after('is_rejection_zone');
            $table->foreignId('linked_quarantine_warehouse_id')->nullable()->after('requires_qc_release')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('linked_rejection_warehouse_id')->nullable()->after('linked_quarantine_warehouse_id')
                ->constrained('warehouses')->nullOnDelete();
        });

        // Add quality status fields to stock table
        Schema::table('stock', function (Blueprint $table) {
            $table->enum('quality_status', [
                'available',           // Ready for use
                'pending_inspection',  // Awaiting QC inspection
                'on_hold',            // Held - no operations allowed
                'conditional',        // Conditional use with restrictions
                'rejected',           // Rejected - awaiting disposition
                'quarantine'          // In quarantine
            ])->default('available')->after('reserved_quantity');

            $table->text('hold_reason')->nullable()->after('quality_status');
            $table->timestamp('hold_until')->nullable()->after('hold_reason');
            $table->json('quality_restrictions')->nullable()->after('hold_until');
            $table->foreignId('quality_hold_by')->nullable()->after('quality_restrictions')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('quality_hold_at')->nullable()->after('quality_hold_by');

            // Reference to inspection/NCR that caused the hold
            $table->string('quality_reference_type', 50)->nullable()->after('quality_hold_at');
            $table->unsignedBigInteger('quality_reference_id')->nullable()->after('quality_reference_type');

            // Indexes
            $table->index(['company_id', 'quality_status']);
            $table->index(['quality_reference_type', 'quality_reference_id']);
        });

        // Add quality tracking to stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('quality_status_from', [
                'available', 'pending_inspection', 'on_hold', 'conditional', 'rejected', 'quarantine'
            ])->nullable()->after('reference_id');
            $table->enum('quality_status_to', [
                'available', 'pending_inspection', 'on_hold', 'conditional', 'rejected', 'quarantine'
            ])->nullable()->after('quality_status_from');
            $table->string('qc_reference_type', 50)->nullable()->after('quality_status_to');
            $table->unsignedBigInteger('qc_reference_id')->nullable()->after('qc_reference_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'quality_status_from',
                'quality_status_to',
                'qc_reference_type',
                'qc_reference_id',
            ]);
        });

        Schema::table('stock', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'quality_status']);
            $table->dropIndex(['quality_reference_type', 'quality_reference_id']);
            $table->dropForeign(['quality_hold_by']);
            $table->dropColumn([
                'quality_status',
                'hold_reason',
                'hold_until',
                'quality_restrictions',
                'quality_hold_by',
                'quality_hold_at',
                'quality_reference_type',
                'quality_reference_id',
            ]);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['linked_quarantine_warehouse_id']);
            $table->dropForeign(['linked_rejection_warehouse_id']);
            $table->dropColumn([
                'is_quarantine_zone',
                'is_rejection_zone',
                'requires_qc_release',
                'linked_quarantine_warehouse_id',
                'linked_rejection_warehouse_id',
            ]);
        });

        // Revert warehouse_type to original enum
        DB::statement("ALTER TABLE warehouses ALTER COLUMN warehouse_type TYPE VARCHAR(50)");
    }
};
