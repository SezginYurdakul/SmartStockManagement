<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Product-specific UOM conversions for MRP II systems.
     *
     * Standard conversions (1 kg = 1000 g) are in units_of_measure table.
     * Product-specific conversions (1 box of product X = 24 pcs) are here.
     *
     * Examples:
     * - 1 Pallet of tractor tires = 24 pcs (but different tire = 32 pcs)
     * - 1 Box of M8 bolts = 500 pcs (but M10 bolts = 250 pcs)
     * - 1 Drum of hydraulic oil = 200 L (but different brand = 208 L)
     */
    public function up(): void
    {
        Schema::create('product_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->foreignId('to_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 20, 6); // from_uom * factor = to_uom
            $table->boolean('is_default')->default(false); // Default conversion for this product
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Each product can have only one conversion between two specific units
            $table->unique(['product_id', 'from_uom_id', 'to_uom_id'], 'product_uom_unique');

            // Indexes for common queries
            $table->index(['company_id', 'product_id']);
            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_uom_conversions');
    }
};
