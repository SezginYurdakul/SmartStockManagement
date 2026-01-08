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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('over_delivery_tolerance_percentage', 5, 2)->nullable()->after('notes')
                ->comment('Over-delivery tolerance percentage for this specific order item. Null means use product, category or system default. This is the most specific level.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('over_delivery_tolerance_percentage');
        });
    }
};
