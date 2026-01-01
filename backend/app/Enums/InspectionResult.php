<?php

namespace App\Enums;

/**
 * Inspection Result Enum
 *
 * Used for type-safe business logic decisions.
 * UI labels are stored in the lookup_values table.
 */
enum InspectionResult: string
{
    case PENDING = 'pending';
    case PASSED = 'passed';
    case FAILED = 'failed';
    case PARTIAL = 'partial';
    case ON_HOLD = 'on_hold';

    /**
     * Check if inspection requires NCR creation
     */
    public function requiresNcr(): bool
    {
        return match ($this) {
            self::FAILED, self::PARTIAL => true,
            default => false,
        };
    }

    /**
     * Check if inspection is complete
     */
    public function isComplete(): bool
    {
        return $this !== self::PENDING;
    }

    /**
     * Check if stock can be released
     */
    public function canReleaseStock(): bool
    {
        return $this === self::PASSED;
    }

    /**
     * Check if requires quarantine
     */
    public function requiresQuarantine(): bool
    {
        return match ($this) {
            self::FAILED, self::ON_HOLD => true,
            default => false,
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
     * Get default fallback label (used if DB lookup fails)
     */
    public function fallbackLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PASSED => 'Passed',
            self::FAILED => 'Failed',
            self::PARTIAL => 'Partial',
            self::ON_HOLD => 'On Hold',
        };
    }
}
