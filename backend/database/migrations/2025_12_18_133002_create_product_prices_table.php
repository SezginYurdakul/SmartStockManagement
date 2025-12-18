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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->enum('price_type', ['base', 'cost', 'wholesale', 'retail', 'special'])->default('base');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('min_quantity', 15, 3)->default(1); // For tiered pricing
            $table->foreignId('customer_group_id')->nullable(); // For customer-specific pricing
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');

            $table->index(['product_id', 'currency_code']);
            $table->index(['product_id', 'price_type']);
            $table->index(['effective_date', 'expiry_date']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
