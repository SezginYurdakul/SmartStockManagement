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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            
            // What happened
            $table->string('event_type', 50); // 'created', 'updated', 'deleted', 'approved', etc.
            $table->string('entity_type', 100); // 'App\Models\Product'
            $table->unsignedBigInteger('entity_id');
            
            // Who did it
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable(); // Snapshot
            $table->string('user_email')->nullable(); // Snapshot
            
            // When
            $table->timestamp('occurred_at');
            
            // What changed (for updates)
            $table->json('changes')->nullable(); // {"field": {"old": "value", "new": "value"}}
            
            // Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            
            // Additional info
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['company_id', 'entity_type', 'entity_id'], 'idx_audit_company_entity');
            $table->index(['user_id', 'occurred_at'], 'idx_audit_user');
            $table->index(['event_type', 'occurred_at'], 'idx_audit_event');
            $table->index('occurred_at', 'idx_audit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
