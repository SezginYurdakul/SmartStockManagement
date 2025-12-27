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
        Schema::create('acceptance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Rule identification
            $table->string('rule_code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Inspection criteria
            $table->enum('inspection_type', ['visual', 'dimensional', 'functional', 'documentation', 'sampling'])->default('visual');
            $table->enum('sampling_method', ['100_percent', 'aql', 'random', 'skip_lot'])->default('100_percent');
            $table->decimal('sample_size_percentage', 5, 2)->nullable(); // For random sampling
            $table->string('aql_level', 20)->nullable(); // e.g., "Level II", "S-2"
            $table->decimal('aql_value', 5, 2)->nullable(); // e.g., 1.0, 2.5, 4.0

            // Acceptance criteria
            $table->json('criteria')->nullable(); // Flexible criteria definitions
            /*
             * Example criteria JSON:
             * {
             *   "dimensions": {"tolerance": "±0.5mm"},
             *   "visual": {"defects_allowed": 0},
             *   "documentation": ["certificate_of_origin", "test_report"]
             * }
             */

            // Rule scope
            $table->boolean('is_default')->default(false); // Default rule for company
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Higher = more specific

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['company_id', 'rule_code']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceptance_rules');
    }
};
