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
        Schema::create('stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('lot_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            // quantity_available is a virtual column (generated)
            $table->decimal('quantity_available', 15, 3)
                ->storedAs('quantity_on_hand - quantity_reserved');
            $table->decimal('unit_cost', 15, 4)->default(0);
            // total_value is a virtual column (generated)
            $table->decimal('total_value', 20, 4)
                ->storedAs('quantity_on_hand * unit_cost');
            $table->date('expiry_date')->nullable();
            $table->date('received_date')->nullable();
            $table->enum('status', ['available', 'reserved', 'quarantine', 'damaged', 'expired'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint for stock location
            $table->unique(
                ['product_id', 'warehouse_id', 'lot_number', 'serial_number'],
                'stock_location_unique'
            );

            // Indexes
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'warehouse_id']);
            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index('lot_number');
            $table->index('serial_number');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
