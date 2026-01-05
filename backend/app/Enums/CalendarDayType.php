<?php

namespace App\Enums;

/**
 * Calendar Day Type Enum (for CRP)
 */
enum CalendarDayType: string
{
    case WORKING = 'working';
    case HOLIDAY = 'holiday';
    case MAINTENANCE = 'maintenance';
    case SHUTDOWN = 'shutdown';

    public function label(): string
    {
        return match ($this) {
            self::WORKING => 'Working Day',
            self::HOLIDAY => 'Holiday',
            self::MAINTENANCE => 'Maintenance',
            self::SHUTDOWN => 'Shutdown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WORKING => 'green',
            self::HOLIDAY => 'blue',
            self::MAINTENANCE => 'orange',
            self::SHUTDOWN => 'red',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::WORKING;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
