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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->enum('warehouse_type', ['finished_goods', 'raw_materials', 'wip', 'returns'])->default('finished_goods');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            // QC-related settings
            $table->boolean('is_quarantine_zone')->default(false)->after('is_default');
            $table->boolean('is_rejection_zone')->default(false)->after('is_quarantine_zone');
            $table->boolean('requires_qc_release')->default(false)->after('is_rejection_zone');
            $table->foreignId('linked_quarantine_warehouse_id')->nullable()->after('requires_qc_release')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('linked_rejection_warehouse_id')->nullable()->after('linked_quarantine_warehouse_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: company + code
            $table->unique(['company_id', 'code']);

            // Indexes
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'warehouse_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
