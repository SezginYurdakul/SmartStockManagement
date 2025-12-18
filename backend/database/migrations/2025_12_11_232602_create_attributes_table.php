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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., 'color', 'size', 'storage' - unique per company
            $table->string('display_name'); // e.g., 'Renk', 'Beden', 'Depolama'
            $table->enum('type', ['select', 'text', 'number', 'boolean'])->default('select');
            $table->integer('order')->default(0); // Display order
            $table->boolean('is_variant_attribute')->default(false); // Can be used for variant generation
            $table->boolean('is_filterable')->default(true); // Show in filters
            $table->boolean('is_visible')->default(true); // Show on product page
            $table->boolean('is_required')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']); // Name unique per company
            $table->index('company_id');
            $table->index('type');
            $table->index('is_variant_attribute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
