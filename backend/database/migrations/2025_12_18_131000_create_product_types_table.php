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
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50); // 'electronics', 'textile', 'food', 'raw_material'
            $table->string('name'); // 'Electronics', 'Textile', 'Food & Beverage'
            $table->text('description')->nullable();
            $table->boolean('can_be_purchased')->default(true);
            $table->boolean('can_be_sold')->default(true);
            $table->boolean('can_be_manufactured')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']); // Code unique per company
            $table->index('company_id');
            $table->index('is_active');
        });

        // Add product_type_id to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_type_id')
                ->nullable()
                ->after('company_id')
                ->constrained('product_types')
                ->nullOnDelete();

            $table->index('product_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_type_id']);
            $table->dropColumn('product_type_id');
        });

        Schema::dropIfExists('product_types');
    }
};
