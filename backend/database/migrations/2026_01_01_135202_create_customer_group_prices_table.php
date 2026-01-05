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
        Schema::create('customer_group_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 15, 4);
            $table->foreignId('currency_id')->nullable()->after('price')->constrained()->nullOnDelete();
            $table->decimal('min_quantity', 15, 4)->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true)->after('valid_to');
            $table->timestamps();

            $table->unique(['customer_group_id', 'product_id', 'min_quantity'], 'cgp_unique');
            $table->index(['product_id', 'valid_from', 'valid_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_group_prices');
    }
};
