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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug'); // Company-scoped unique (composite index below)
            $table->string('sku'); // Unique per company, handled by composite index
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            // category_id removed - now using category_product pivot table
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            
            // MRP Planning fields
            $table->unsignedSmallInteger('lead_time_days')->default(0)->after('is_active');
            $table->decimal('safety_stock', 15, 4)->default(0)->after('lead_time_days');
            $table->decimal('reorder_point', 15, 4)->default(0)->after('safety_stock');
            $table->string('make_or_buy', 10)->default('buy')->after('reorder_point');
            $table->unsignedSmallInteger('low_level_code')->default(0)->after('make_or_buy');
            $table->decimal('minimum_order_qty', 15, 4)->default(1)->after('low_level_code');
            $table->decimal('order_multiple', 15, 4)->default(1)->after('minimum_order_qty');
            $table->decimal('maximum_stock', 15, 4)->nullable()->after('order_multiple');
            
            // Negative stock policy
            $table->string('negative_stock_policy', 20)->default('NEVER')->after('maximum_stock');
            $table->decimal('negative_stock_limit', 15, 3)->default(0)->after('negative_stock_policy');
            
            // Reservation policy
            $table->string('reservation_policy', 20)->default('full')->after('negative_stock_limit');
            
            // Over-delivery tolerance
            $table->decimal('over_delivery_tolerance_percentage', 5, 2)->nullable()->after('reservation_policy')
                ->comment('Over-delivery tolerance percentage for this product. Null means use category or system default.');
            
            $table->json('meta_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique constraints: company-scoped uniqueness
            $table->unique(['company_id', 'slug'], 'products_company_slug_unique'); // Slug unique per company
            $table->unique(['company_id', 'sku']); // SKU unique per company
            $table->index('company_id');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index(['make_or_buy', 'is_active'], 'idx_products_mrp');
            $table->index('low_level_code', 'idx_products_low_level_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
