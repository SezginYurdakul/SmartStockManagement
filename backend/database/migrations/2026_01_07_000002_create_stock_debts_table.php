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
        Schema::create('stock_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            
            // Debt information
            $table->decimal('quantity', 15, 3)->comment('Debt amount (positive value)');
            $table->decimal('reconciled_quantity', 15, 3)->default(0)->comment('Settled amount');
            $table->decimal('outstanding_quantity', 15, 3)->storedAs('quantity - reconciled_quantity');
            
            // Reference to what caused this debt
            $table->string('reference_type', 50)->nullable()->comment('DeliveryNote, WorkOrder, etc.');
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->timestamp('reconciled_at')->nullable();
            
            // Indexes
            $table->index(['company_id', 'product_id', 'warehouse_id', 'outstanding_quantity'], 'idx_stock_debts_outstanding');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_debts_reference');
            $table->index(['created_at'], 'idx_stock_debts_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_debts');
    }
};
