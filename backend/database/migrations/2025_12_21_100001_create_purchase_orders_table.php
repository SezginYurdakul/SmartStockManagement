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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('order_number', 50);
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('restrict');
            $table->unsignedBigInteger('mrp_recommendation_id')->nullable();
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');

            // Dates
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            // Status workflow
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'sent',
                'partially_received',
                'received',
                'cancelled',
                'closed'
            ])->default('draft');

            // Currency
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 15, 6)->default(1.000000);

            // Amounts
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Payment
            $table->string('payment_terms', 100)->nullable();
            $table->integer('payment_due_days')->nullable();

            // Shipping
            $table->string('shipping_method', 100)->nullable();
            $table->text('shipping_address')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('meta_data')->nullable();

            // Approval workflow
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + order_number
            $table->unique(['company_id', 'order_number']);

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'order_date']);
            $table->index('expected_delivery_date');
            $table->index('mrp_recommendation_id');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('line_number')->default(1);

            // Description
            $table->string('description', 500)->nullable();

            // Quantities
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('quantity_cancelled', 15, 3)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Pricing
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 2);

            // Delivery
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            // Tracking
            $table->string('lot_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('over_delivery_tolerance_percentage', 5, 2)->nullable()->after('notes')
                ->comment('Over-delivery tolerance percentage for this specific order item. Null means use product, category or system default. This is the most specific level.');

            $table->timestamps();

            // Indexes
            $table->index(['purchase_order_id', 'line_number']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
