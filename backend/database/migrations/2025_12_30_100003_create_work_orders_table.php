<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Work Order Header
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Product being manufactured
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('bom_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('routing_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('mrp_recommendation_id')->nullable();

            // Identification
            $table->string('work_order_number', 50);

            // Quantities
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_completed', 15, 3)->default(0);
            $table->decimal('quantity_scrapped', 15, 3)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Warehouse for finished goods
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');

            // Status and priority
            $table->enum('status', [
                'draft',
                'released',
                'in_progress',
                'completed',
                'cancelled',
                'on_hold'
            ])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Planned dates
            $table->dateTime('planned_start_date')->nullable();
            $table->dateTime('planned_end_date')->nullable();

            // Actual dates
            $table->dateTime('actual_start_date')->nullable();
            $table->dateTime('actual_end_date')->nullable();

            // Cost tracking
            $table->decimal('estimated_cost', 15, 4)->default(0);
            $table->decimal('actual_cost', 15, 4)->default(0);

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('meta_data')->nullable();

            // Workflow tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + work_order_number
            $table->unique(['company_id', 'work_order_number']);

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'priority', 'status']);
            $table->index('planned_start_date');
            $table->index('planned_end_date');
            $table->index('mrp_recommendation_id');
        });

        // Work Order Operations
        Schema::create('work_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('routing_operation_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('work_center_id')->constrained()->onDelete('restrict');

            // Operation info (copied from routing)
            $table->integer('operation_number');
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Status
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');

            // Quantities
            $table->decimal('quantity_completed', 15, 3)->default(0);
            $table->decimal('quantity_scrapped', 15, 3)->default(0);

            // Planned times
            $table->dateTime('planned_start')->nullable();
            $table->dateTime('planned_end')->nullable();

            // Actual times
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();

            // Actual time spent (in minutes)
            $table->decimal('actual_setup_time', 10, 2)->default(0);
            $table->decimal('actual_run_time', 10, 2)->default(0);

            // Cost
            $table->decimal('actual_cost', 15, 4)->default(0);

            // Notes
            $table->text('notes')->nullable();

            // Tracking
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['work_order_id', 'operation_number']);
            $table->index(['work_order_id', 'status']);
            $table->index('work_center_id');

            // Unique operation number per work order
            $table->unique(['work_order_id', 'operation_number']);
        });

        // Work Order Material Consumption (issued materials)
        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('bom_item_id')->nullable()->constrained()->onDelete('set null');

            // Quantities
            $table->decimal('quantity_required', 15, 4);
            $table->decimal('quantity_issued', 15, 4)->default(0);
            $table->decimal('quantity_returned', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Source
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');

            // Cost
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['work_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_materials');
        Schema::dropIfExists('work_order_operations');
        Schema::dropIfExists('work_orders');
    }
};
