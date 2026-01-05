<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Company calendars define special working days and holidays for MRP calculations.
     * This allows admins to override standard working days for specific dates.
     */
    public function up(): void
    {
        Schema::create('company_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            
            // Calendar date
            $table->date('calendar_date');
            
            // Day type: 'working' (override holiday to working), 'holiday' (override working to holiday)
            $table->enum('day_type', ['working', 'holiday'])->default('holiday');
            
            // Shift information (optional - if null, uses default shift from settings)
            $table->string('shift_name', 50)->nullable(); // e.g., 'morning', 'afternoon', 'night'
            $table->time('shift_start')->nullable(); // Override shift start time
            $table->time('shift_end')->nullable(); // Override shift end time
            $table->decimal('break_hours', 4, 2)->nullable(); // Override break hours
            
            // Working hours for this day (calculated or manual override)
            $table->decimal('working_hours', 6, 2)->nullable();
            
            // Reason/description
            $table->string('reason', 255)->nullable(); // e.g., 'National Holiday', 'Company Event', 'Maintenance'
            $table->text('notes')->nullable();
            
            // Recurring pattern (optional)
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['yearly', 'monthly', 'weekly'])->nullable();
            $table->json('recurrence_pattern')->nullable(); // Additional recurrence data
            
            $table->timestamps();
            
            // Unique: one entry per company per date
            $table->unique(['company_id', 'calendar_date'], 'uk_company_calendar_date');
            
            // Indexes
            $table->index(['company_id', 'calendar_date', 'day_type'], 'idx_company_calendar_lookup');
            $table->index(['company_id', 'calendar_date'], 'idx_company_calendar_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_calendars');
    }
};
