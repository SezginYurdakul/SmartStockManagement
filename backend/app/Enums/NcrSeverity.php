<?php

namespace App\Enums;

/**
 * NCR Severity Enum
 *
 * Determines the severity level and corresponding actions.
 * UI labels are stored in the lookup_values table.
 */
enum NcrSeverity: string
{
    case MINOR = 'minor';
    case MAJOR = 'major';
    case CRITICAL = 'critical';

    /**
     * Check if auto-quarantine is required
     */
    public function requiresQuarantine(): bool
    {
        return $this === self::CRITICAL;
    }

    /**
     * Check if immediate notification is required
     */
    public function requiresImmediateNotification(): bool
    {
        return in_array($this, [self::CRITICAL, self::MAJOR]);
    }

    /**
     * Check if management escalation is required
     */
    public function requiresEscalation(): bool
    {
        return $this === self::CRITICAL;
    }

    /**
     * Get response time in hours
     */
    public function responseTimeHours(): int
    {
        return match ($this) {
            self::CRITICAL => 4,
            self::MAJOR => 24,
            self::MINOR => 72,
        };
    }

    /**
     * Get priority color for UI
     */
    public function color(): string
    {
        return match ($this) {
            self::CRITICAL => 'red',
            self::MAJOR => 'orange',
            self::MINOR => 'yellow',
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
     * Get default fallback label
     */
    public function fallbackLabel(): string
    {
        return match ($this) {
            self::MINOR => 'Minor',
            self::MAJOR => 'Major',
            self::CRITICAL => 'Critical',
        };
    }
}
