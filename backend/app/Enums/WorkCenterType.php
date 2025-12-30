<?php

namespace App\Enums;

/**
 * Work Center Type Enum
 *
 * Defines types of work centers in manufacturing.
 */
enum WorkCenterType: string
{
    case MACHINE = 'machine';
    case LABOR = 'labor';
    case SUBCONTRACT = 'subcontract';
    case TOOL = 'tool';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::MACHINE => 'Machine',
            self::LABOR => 'Labor',
            self::SUBCONTRACT => 'Subcontract',
            self::TOOL => 'Tool',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::MACHINE => 'Machine-based work center (CNC, lathe, etc.)',
            self::LABOR => 'Labor-intensive work center (assembly, inspection)',
            self::SUBCONTRACT => 'Outsourced operations to third party',
            self::TOOL => 'Tool or equipment based operations',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all as options for dropdown
     */
    public static function options(): array
    {
        return array_map(
            fn(self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
