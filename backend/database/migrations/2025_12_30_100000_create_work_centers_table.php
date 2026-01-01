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
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Work center type
            $table->enum('work_center_type', ['machine', 'labor', 'subcontract', 'tool'])->default('machine');

            // Costing
            $table->decimal('cost_per_hour', 15, 4)->default(0);
            $table->string('cost_currency', 3)->default('USD');

            // Capacity
            $table->decimal('capacity_per_day', 15, 3)->default(8); // Hours per day
            $table->decimal('efficiency_percentage', 5, 2)->default(100.00);

            // Status
            $table->boolean('is_active')->default(true);

            // Configuration
            $table->json('settings')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + code
            $table->unique(['company_id', 'code']);

            // Indexes
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'work_center_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
