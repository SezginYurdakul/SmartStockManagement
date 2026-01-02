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
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->integer('payment_terms_days')->nullable()->after('discount_percentage');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('payment_terms_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'credit_limit']);
        });
    }
};
