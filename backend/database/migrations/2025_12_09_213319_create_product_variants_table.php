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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Small - Red", "Large - Blue"
            $table->string('sku'); // Unique per product (composite index below)
            $table->decimal('price', 10, 2)->nullable(); // If null, uses product price
            $table->integer('stock')->default(0);
            $table->json('attributes'); // e.g., {"size": "L", "color": "Red"}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Composite unique constraint: product_id + sku (variants belong to products, which are company-scoped)
            $table->unique(['product_id', 'sku'], 'product_variants_product_sku_unique');
            $table->index('product_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
