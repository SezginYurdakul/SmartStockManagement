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
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20); // 'kg', 'lbs', 'pcs', 'l', 'm', 'box'
            $table->string('name', 100); // 'Kilogram', 'Pound', 'Piece', 'Liter'
            $table->enum('uom_type', ['weight', 'volume', 'length', 'area', 'quantity', 'time'])->default('quantity');
            $table->foreignId('base_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('conversion_factor', 20, 6)->nullable(); // Conversion to base unit
            $table->integer('precision')->default(2); // Decimal places
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']); // Code unique per company
            $table->index('company_id');
            $table->index('uom_type');
            $table->index('is_active');
        });

        // Add uom_id to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('uom_id')
                ->nullable()
                ->after('product_type_id')
                ->constrained('units_of_measure')
                ->nullOnDelete();

            $table->index('uom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn('uom_id');
        });

        Schema::dropIfExists('units_of_measure');
    }
};
