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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();

            // Movement type
            $table->enum('movement_type', [
                'receipt',
                'issue',
                'transfer',
                'adjustment',
                'production_consume',
                'production_output',
                'return',
                'scrap'
            ]);

            // Transaction type (source of movement)
            $table->enum('transaction_type', [
                'purchase_order',
                'sales_order',
                'production_order',
                'transfer_order',
                'adjustment',
                'initial_stock',
                'return',
                'scrap'
            ]);

            // Reference to source document
            $table->string('reference_number', 100)->nullable();
            $table->string('reference_type', 100)->nullable(); // Polymorphic: App\Models\PurchaseOrder
            $table->unsignedBigInteger('reference_id')->nullable();

            // Quantities
            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_before', 15, 3)->default(0);
            $table->decimal('quantity_after', 15, 3)->default(0);

            // Costs
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 20, 4)->default(0);

            // Additional info
            $table->text('notes')->nullable();
            $table->json('meta_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'product_id', 'created_at']);
            $table->index(['company_id', 'warehouse_id', 'created_at']);
            $table->index(['product_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('reference_number');
            $table->index('movement_type');
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
