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
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('grn_number', 50);
            $table->foreignId('purchase_order_id')->constrained()->onDelete('restrict');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');

            // Dates
            $table->date('received_date');
            $table->string('delivery_note_number', 100)->nullable();
            $table->date('delivery_note_date')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();

            // Status
            $table->enum('status', [
                'draft',
                'pending_inspection',
                'inspected',
                'completed',
                'cancelled'
            ])->default('draft');

            // Inspection
            $table->boolean('requires_inspection')->default(false);
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->text('inspection_notes')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->json('meta_data')->nullable();

            // Tracking
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint
            $table->unique(['company_id', 'grn_number']);

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'received_date']);
            $table->index('purchase_order_id');
            $table->index('supplier_id');
        });

        Schema::create('goods_received_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->constrained()->onDelete('restrict');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('line_number')->default(1);

            // Quantities
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_accepted', 15, 3)->default(0);
            $table->decimal('quantity_rejected', 15, 3)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Costing
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 20, 4);

            // Tracking
            $table->string('lot_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufacture_date')->nullable();

            // Storage
            $table->string('storage_location', 100)->nullable();
            $table->string('bin_location', 50)->nullable();

            // Inspection
            $table->enum('inspection_status', [
                'pending',
                'passed',
                'failed',
                'partial'
            ])->nullable();
            $table->text('inspection_notes')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['goods_received_note_id', 'line_number']);
            $table->index('product_id');
            $table->index('lot_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
    }
};
