<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_group_prices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->nullable()->after('price')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('valid_to');
        });

        // Update company_id from customer_group
        DB::statement('
            UPDATE customer_group_prices cgp
            SET company_id = cg.company_id
            FROM customer_groups cg
            WHERE cgp.customer_group_id = cg.id
        ');

        // Make company_id not null after update
        Schema::table('customer_group_prices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_group_prices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['company_id', 'currency_id', 'is_active']);
        });
    }
};
