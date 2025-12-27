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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();           // e.g., 'qc', 'inventory', 'general'
            $table->string('key', 100);                      // e.g., 'sampling_methods', 'inspection_types'
            $table->json('value');                           // JSON data
            $table->string('description')->nullable();       // Human-readable description
            $table->boolean('is_system')->default(false);    // System settings can't be deleted
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
