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
        // Routing Header
        Schema::create('routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Identification
            $table->string('routing_number', 50);
            $table->integer('version')->default(1);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Status
            $table->enum('status', ['draft', 'active', 'obsolete'])->default('draft');

            // Flags
            $table->boolean('is_default')->default(false);

            // Effectivity
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->json('meta_data')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + routing_number
            $table->unique(['company_id', 'routing_number']);

            // Indexes
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'status']);
            $table->index(['product_id', 'is_default']);
        });

        // Routing Operations
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_center_id')->constrained()->onDelete('restrict');

            // Operation info
            $table->integer('operation_number');
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Time estimates (in minutes)
            $table->decimal('setup_time', 10, 2)->default(0); // Setup time in minutes
            $table->decimal('run_time_per_unit', 10, 4)->default(0); // Run time per unit in minutes
            $table->decimal('queue_time', 10, 2)->default(0); // Wait time before operation
            $table->decimal('move_time', 10, 2)->default(0); // Move time to next operation

            // Subcontracting
            $table->boolean('is_subcontracted')->default(false);
            $table->foreignId('subcontractor_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('subcontract_cost', 15, 4)->nullable();

            // Instructions
            $table->text('instructions')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['routing_id', 'operation_number']);
            $table->index('work_center_id');

            // Unique operation number per routing
            $table->unique(['routing_id', 'operation_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('routings');
    }
};
