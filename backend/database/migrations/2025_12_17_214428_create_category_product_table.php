<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create pivot table
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'product_id']);
            $table->index(['product_id', 'is_primary']);
        });

        // Migrate existing category_id data to pivot table
        DB::statement('
            INSERT INTO category_product (category_id, product_id, is_primary, created_at, updated_at)
            SELECT category_id, id, true, NOW(), NOW()
            FROM products
            WHERE category_id IS NOT NULL
        ');

        // Remove category_id column from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add category_id column back
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('low_stock_threshold')->constrained()->onDelete('set null');
        });

        // Restore primary category data
        DB::statement('
            UPDATE products p
            SET category_id = (
                SELECT category_id
                FROM category_product cp
                WHERE cp.product_id = p.id AND cp.is_primary = true
                LIMIT 1
            )
        ');

        // Drop pivot table
        Schema::dropIfExists('category_product');
    }
};
