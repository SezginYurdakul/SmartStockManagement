<?php

namespace App\Models;

use App\Enums\CalendarDayType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterCalendar extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_center_id',
        'calendar_date',
        'shift_start',
        'shift_end',
        'break_hours',
        'available_hours',
        'efficiency_override',
        'capacity_override',
        'day_type',
        'notes',
    ];

    protected $casts = [
        'calendar_date' => 'date',
        'break_hours' => 'decimal:2',
        'available_hours' => 'decimal:2',
        'efficiency_override' => 'decimal:2',
        'capacity_override' => 'decimal:2',
        'day_type' => CalendarDayType::class,
    ];

    // =========================================
    // Relationships
    // =========================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
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
        return $query->where('day_type', CalendarDayType::WORKING);
    }

    public function scopeNonWorking($query)
    {
        return $query->where('day_type', '!=', CalendarDayType::WORKING);
    }

    // =========================================
    // Capacity Calculations
    // =========================================

    /**
     * Get the effective available hours for this day
     */
    public function getEffectiveHoursAttribute(): float
    {
        if (!$this->day_type->isAvailable()) {
            return 0;
        }

        // Use capacity override if set, otherwise use available_hours
        $hours = $this->capacity_override ?? $this->available_hours;

        // Apply efficiency
        $efficiency = $this->getEffectiveEfficiency() / 100;

        return $hours * $efficiency;
    }

    /**
     * Get the effective efficiency percentage
     */
    public function getEffectiveEfficiency(): float
    {
        // Use override if set, otherwise get from work center
        return $this->efficiency_override
            ?? $this->workCenter?->efficiency_percentage
            ?? 100;
    }

    /**
     * Calculate hours from shift times
     */
    public function calculateShiftHours(): float
    {
        if (!$this->shift_start || !$this->shift_end) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->shift_start);
        $end = \Carbon\Carbon::parse($this->shift_end);

        $totalMinutes = $start->diffInMinutes($end);
        $breakMinutes = ($this->break_hours ?? 0) * 60;

        return max(0, ($totalMinutes - $breakMinutes) / 60);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Check if this day is available for work
     */
    public function isAvailable(): bool
    {
        return $this->day_type->isAvailable();
    }

    /**
     * Check if this day has reduced capacity
     */
    public function hasReducedCapacity(): bool
    {
        if (!$this->isAvailable()) {
            return true;
        }

        $normalHours = $this->workCenter?->capacity_per_day ?? 8;
        return $this->effective_hours < $normalHours;
    }

    // =========================================
    // Static Helpers
    // =========================================

    /**
     * Generate default calendar entries for a work center
     */
    public static function generateForWorkCenter(
        WorkCenter $workCenter,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $holidays = []
    ): int {
        $count = 0;
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current <= $end) {
            // Check if entry already exists
            $exists = static::where('work_center_id', $workCenter->id)
                ->where('calendar_date', $current->toDateString())
                ->exists();

            if (!$exists) {
                $dayType = CalendarDayType::WORKING;
                $notes = null;

                // Check if weekend
                if ($current->isWeekend()) {
                    $dayType = CalendarDayType::HOLIDAY;
                    $notes = 'Weekend';
                }

                // Check if in holidays array
                $dateString = $current->toDateString();
                if (isset($holidays[$dateString])) {
                    $dayType = CalendarDayType::HOLIDAY;
                    $notes = $holidays[$dateString];
                }

                static::create([
                    'company_id' => $workCenter->company_id,
                    'work_center_id' => $workCenter->id,
                    'calendar_date' => $dateString,
                    'shift_start' => '08:00:00',
                    'shift_end' => '17:00:00',
                    'break_hours' => 1.00,
                    'available_hours' => $dayType->isAvailable() ? $workCenter->capacity_per_day : 0,
                    'day_type' => $dayType,
                    'notes' => $notes,
                ]);

                $count++;
            }

            $current->addDay();
        }

        return $count;
    }

    /**
     * Get total available hours for a work center in date range
     */
    public static function getTotalAvailableHours(
        int $workCenterId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        return static::where('work_center_id', $workCenterId)
            ->dateRange($startDate, $endDate)
            ->working()
            ->get()
            ->sum(fn($cal) => $cal->effective_hours);
    }
}
