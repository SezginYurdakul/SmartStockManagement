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
        Schema::create('non_conformance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Source reference (where the NCR originated)
            $table->enum('source_type', ['receiving', 'production', 'internal', 'customer'])->default('receiving');
            $table->foreignId('receiving_inspection_id')->nullable()->constrained()->nullOnDelete();
            // Future: production_inspection_id for manufacturing QC

            // NCR identification
            $table->string('ncr_number', 50);
            $table->string('title', 255);
            $table->text('description');

            // Related entities
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->string('batch_number', 100)->nullable();

            // Quantity and severity
            $table->decimal('quantity_affected', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // NCR details
            $table->enum('defect_type', [
                'dimensional',
                'visual',
                'functional',
                'documentation',
                'packaging',
                'contamination',
                'wrong_item',
                'quantity_short',
                'quantity_over',
                'damage',
                'other'
            ])->default('other');
            $table->text('root_cause')->nullable();

            // Disposition/Resolution
            $table->enum('disposition', [
                'pending',
                'use_as_is',
                'rework',
                'scrap',
                'return_to_supplier',
                'sort_and_use',
                'reject'
            ])->default('pending');
            $table->text('disposition_reason')->nullable();
            $table->decimal('cost_impact', 15, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();

            // Workflow status
            $table->enum('status', [
                'open',
                'under_review',
                'pending_disposition',
                'disposition_approved',
                'in_progress',
                'closed',
                'cancelled'
            ])->default('open');

            // Attachments/Evidence (stored as JSON array of file paths)
            $table->json('attachments')->nullable();

            // Workflow timestamps and users
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reported_at');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('disposition_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disposition_at')->nullable();

            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['company_id', 'ncr_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'severity']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'reported_at']);
            $table->index(['company_id', 'source_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_conformance_reports');
    }
};
