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
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('line_number')->default(1);
            $table->string('description', 500)->nullable();
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('quantity_shipped', 15, 4)->default(0);
            $table->decimal('quantity_cancelled', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->decimal('over_delivery_tolerance_percentage', 5, 2)->nullable()->after('notes')
                ->comment('Over-delivery tolerance percentage for this specific order item. Null means use product, category or system default. This is the most specific level.');
            $table->timestamps();

            $table->index(['sales_order_id', 'line_number']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
