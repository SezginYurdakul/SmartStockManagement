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
        Schema::create('receiving_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grn_item_id')->constrained('goods_received_note_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acceptance_rule_id')->nullable()->constrained()->nullOnDelete();

            // Inspection identification
            $table->string('inspection_number', 50);
            $table->string('lot_number', 100)->nullable();
            $table->string('batch_number', 100)->nullable();

            // Quantities
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_inspected', 15, 4);
            $table->decimal('quantity_passed', 15, 4)->default(0);
            $table->decimal('quantity_failed', 15, 4)->default(0);
            $table->decimal('quantity_on_hold', 15, 4)->default(0);

            // Inspection result
            $table->enum('result', ['pending', 'passed', 'failed', 'partial', 'on_hold'])->default('pending');
            $table->enum('disposition', ['accept', 'reject', 'rework', 'return_to_supplier', 'use_as_is', 'pending'])->default('pending');

            // Inspection details
            $table->json('inspection_data')->nullable(); // Stores actual measurements/checks
            /*
             * Example inspection_data JSON:
             * {
             *   "visual": {"result": "pass", "notes": "No visible defects"},
             *   "dimensional": {"length": 100.2, "width": 50.1, "result": "pass"},
             *   "documentation": {"certificate": true, "test_report": true}
             * }
             */
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();

            // Timestamps for workflow
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['company_id', 'inspection_number']);
            $table->index(['company_id', 'goods_received_note_id']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'result']);
            $table->index(['company_id', 'inspected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_inspections');
    }
};
