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
        // BOM Header
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Identification
            $table->string('bom_number', 50);
            $table->integer('version')->default(1);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Type and status
            $table->enum('bom_type', ['manufacturing', 'engineering', 'phantom'])->default('manufacturing');
            $table->enum('status', ['draft', 'active', 'obsolete'])->default('draft');

            // Quantity info
            $table->decimal('quantity', 15, 4)->default(1); // Base quantity for BOM
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Flags
            $table->boolean('is_default')->default(false);

            // Effectivity dates
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->json('meta_data')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + bom_number
            $table->unique(['company_id', 'bom_number']);

            // Indexes
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'status']);
            $table->index(['product_id', 'is_default']);
        });

        // BOM Items (Components)
        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->onDelete('cascade');
            $table->foreignId('component_id')->constrained('products')->onDelete('restrict');

            // Line info
            $table->integer('line_number')->default(1);

            // Quantity
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Scrap/yield
            $table->decimal('scrap_percentage', 5, 2)->default(0);

            // Flags
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_phantom')->default(false); // Pass-through item

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['bom_id', 'line_number']);
            $table->index('component_id');

            // Prevent duplicate component in same BOM
            $table->unique(['bom_id', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
    }
};
