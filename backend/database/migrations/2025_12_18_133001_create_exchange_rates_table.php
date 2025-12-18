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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->date('effective_date');
            $table->string('source', 50)->default('manual'); // 'manual', 'api', 'central_bank'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('from_currency')->references('code')->on('currencies')->onDelete('cascade');
            $table->foreign('to_currency')->references('code')->on('currencies')->onDelete('cascade');

            $table->unique(['from_currency', 'to_currency', 'effective_date'], 'exchange_rate_unique');
            $table->index(['from_currency', 'to_currency']);
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
