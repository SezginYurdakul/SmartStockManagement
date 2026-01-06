<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates MRP (Material Requirements Planning) tables:
     * - mrp_runs: Stores MRP calculation runs
     * - mrp_recommendations: Generated recommendations from MRP runs
     */
    public function up(): void
    {
        // MRP Runs - Each MRP calculation run is logged here
        Schema::create('mrp_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Run identification
            $table->string('run_number', 50);
            $table->string('name', 255)->nullable();

            // Run parameters
            $table->date('planning_horizon_start');
            $table->date('planning_horizon_end');
            $table->boolean('include_safety_stock')->default(true);
            $table->boolean('respect_lead_times')->default(true);
            $table->boolean('consider_wip')->default(true); // Work in progress
            $table->boolean('net_change')->default(false); // Full vs net change MRP

            // Filters (optional)
            $table->json('product_filters')->nullable(); // Specific products/categories
            $table->json('warehouse_filters')->nullable(); // Specific warehouses

            // Run status
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            // Statistics
            $table->unsignedInteger('products_processed')->default(0);
            $table->unsignedInteger('recommendations_generated')->default(0);
            $table->unsignedInteger('warnings_count')->default(0);
            $table->json('warnings_summary')->nullable()->after('warnings_count');

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique run number per company
            $table->unique(['company_id', 'run_number']);

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });

        // MRP Recommendations - Generated suggestions from MRP runs
        Schema::create('mrp_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            // Recommendation type
            $table->enum('recommendation_type', [
                'purchase_order',    // Buy from supplier
                'work_order',        // Manufacture in-house
                'transfer',          // Transfer between warehouses
                'reschedule_in',     // Move order date earlier
                'reschedule_out',    // Move order date later
                'cancel',            // Cancel existing order
                'expedite',          // Expedite existing order
            ]);

            // Timing
            $table->date('required_date');      // When the material is needed
            $table->date('suggested_date');     // When to place order (considering lead time)
            $table->date('due_date');           // When order should arrive

            // Quantities
            $table->decimal('gross_requirement', 15, 4); // Total needed
            $table->decimal('net_requirement', 15, 4);   // After considering stock
            $table->decimal('suggested_quantity', 15, 4); // Recommended order qty
            $table->decimal('current_stock', 15, 4);      // Stock at calculation time
            $table->decimal('projected_stock', 15, 4);    // Expected stock after action

            // Source of demand (what triggered this recommendation)
            $table->string('demand_source_type', 50)->nullable(); // work_order, sales_order, forecast
            $table->unsignedBigInteger('demand_source_id')->nullable();

            // Priority and urgency
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_urgent')->default(false); // Past due or near lead time
            $table->text('urgency_reason')->nullable();

            // Action status
            $table->enum('status', ['pending', 'approved', 'rejected', 'actioned', 'expired'])->default('pending');
            $table->timestamp('actioned_at')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_reference_type', 50)->nullable(); // purchase_order, work_order
            $table->unsignedBigInteger('action_reference_id')->nullable();
            $table->text('action_notes')->nullable();

            // Calculation details (for audit/debugging)
            $table->json('calculation_details')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['mrp_run_id', 'status']);
            $table->index(['company_id', 'product_id', 'status']);
            $table->index(['company_id', 'recommendation_type', 'status']);
            $table->index(['company_id', 'required_date']);
            $table->index(['company_id', 'priority', 'status']);
        });

        // Add foreign key constraints to work_orders and purchase_orders
        // (These tables are created before mrp_recommendations, so we add FK here)
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'mrp_recommendation_id')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->foreign('mrp_recommendation_id')
                    ->references('id')
                    ->on('mrp_recommendations')
                    ->onDelete('set null');
            });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'mrp_recommendation_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreign('mrp_recommendation_id')
                    ->references('id')
                    ->on('mrp_recommendations')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mrp_recommendations');
        Schema::dropIfExists('mrp_runs');
    }
};
