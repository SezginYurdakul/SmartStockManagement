<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyCalendar extends Model
{
    protected $fillable = [
        'company_id',
        'calendar_date',
        'day_type',
        'shift_name',
        'shift_start',
        'shift_end',
        'break_hours',
        'working_hours',
        'reason',
        'notes',
        'is_recurring',
        'recurrence_type',
        'recurrence_pattern',
    ];

    protected $casts = [
        'calendar_date' => 'date',
        'break_hours' => 'decimal:2',
        'working_hours' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeForDate($query, $date)
    {
        return $query->where('calendar_date', $date);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('calendar_date', [$startDate, $endDate]);
    }

    public function scopeWorking($query)
    {
        return $query->where('day_type', 'working');
    }

    public function scopeHoliday($query)
    {
        return $query->where('day_type', 'holiday');
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Check if this is a working day
     */
    public function isWorkingDay(): bool
    {
        return $this->day_type === 'working';
    }

    /**
     * Check if this is a holiday
     */
    public function isHoliday(): bool
    {
        return $this->day_type === 'holiday';
    }

    /**
     * Get effective working hours for this day
     */
    public function getEffectiveWorkingHours(): ?float
    {
        if ($this->working_hours !== null) {
            return (float) $this->working_hours;
        }

        if ($this->shift_start && $this->shift_end) {
            $start = \Carbon\Carbon::parse($this->shift_start);
            $end = \Carbon\Carbon::parse($this->shift_end);
            $totalMinutes = $start->diffInMinutes($end);
            $breakMinutes = ($this->break_hours ?? 0) * 60;
            return max(0, ($totalMinutes - $breakMinutes) / 60);
        }

        return null; // Use default from settings
    }
}
