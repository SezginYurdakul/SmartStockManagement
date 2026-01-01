<?php

namespace App\Enums;

/**
 * BOM Type Enum
 *
 * Defines types of Bill of Materials.
 */
enum BomType: string
{
    case MANUFACTURING = 'manufacturing';
    case ENGINEERING = 'engineering';
    case PHANTOM = 'phantom';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUFACTURING => 'Manufacturing',
            self::ENGINEERING => 'Engineering',
            self::PHANTOM => 'Phantom',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::MANUFACTURING => 'Standard production BOM used for manufacturing',
            self::ENGINEERING => 'Engineering BOM for design/development purposes',
            self::PHANTOM => 'Phantom BOM - components pass through to parent',
        };
    }

    /**
     * Check if BOM is used for production
     */
    public function isProduction(): bool
    {
        return $this === self::MANUFACTURING;
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
