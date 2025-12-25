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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('supplier_code', 50);
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Contact person
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();

            // Financial
            $table->string('currency', 3)->default('USD');
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('bank_iban', 50)->nullable();
            $table->string('bank_swift', 20)->nullable();

            // Logistics
            $table->integer('lead_time_days')->default(0);
            $table->decimal('minimum_order_amount', 15, 2)->nullable();
            $table->string('shipping_method', 100)->nullable();

            // Rating & Notes
            $table->tinyInteger('rating')->nullable(); // 1-5
            $table->text('notes')->nullable();
            $table->json('meta_data')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + supplier_code
            $table->unique(['company_id', 'supplier_code']);

            // Indexes
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'name']);
            $table->index('email');
        });

        // Supplier Products pivot table (which products a supplier can provide)
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('supplier_sku', 100)->nullable(); // Supplier's SKU
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('minimum_order_qty', 15, 3)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
            $table->index(['product_id', 'is_preferred']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
    }
};
