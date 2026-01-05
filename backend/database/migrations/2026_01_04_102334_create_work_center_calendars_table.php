<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Work center calendars define available capacity for CRP calculations.
     * Each entry represents a specific date's working hours and availability.
     */
    public function up(): void
    {
        Schema::create('work_center_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_center_id')->constrained()->onDelete('cascade');

            // Calendar date
            $table->date('calendar_date');

            // Working hours
            $table->time('shift_start')->default('08:00:00');
            $table->time('shift_end')->default('17:00:00');
            $table->decimal('break_hours', 4, 2)->default(1.00); // Lunch/breaks

            // Available capacity in hours (calculated: shift_end - shift_start - break_hours)
            $table->decimal('available_hours', 6, 2)->default(8.00);

            // Capacity adjustments
            $table->decimal('efficiency_override', 5, 2)->nullable(); // Override work center default
            $table->decimal('capacity_override', 6, 2)->nullable(); // Override available hours

            // Day type
            $table->enum('day_type', ['working', 'holiday', 'maintenance', 'shutdown'])->default('working');

            // Notes (reason for holiday, maintenance details, etc.)
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            // Unique: one entry per work center per date
            $table->unique(['work_center_id', 'calendar_date'], 'uk_wc_calendar_date');

            // Indexes for CRP queries
            $table->index(['company_id', 'calendar_date'], 'idx_wc_calendar_company_date');
            $table->index(['work_center_id', 'calendar_date', 'day_type'], 'idx_wc_calendar_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_center_calendars');
    }
};
