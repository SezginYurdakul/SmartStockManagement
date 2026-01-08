<?php

namespace App\Enums;

/**
 * Reservation Policy Enum
 *
 * Defines how to handle stock reservations when insufficient stock is available.
 */
enum ReservationPolicy: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
    case REJECT = 'reject';
    /**
     * Wait for full stock to become available
     * 
     * TODO: Future implementation - This policy will queue reservation requests
     * and automatically retry when stock becomes available. Currently throws
     * an error. Requires queue/retry mechanism implementation.
     */
    case WAIT = 'wait';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::FULL => 'Full Reservation Only',
            self::PARTIAL => 'Allow Partial Reservation',
            self::REJECT => 'Reject if Insufficient',
            self::WAIT => 'Wait for Full Stock',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match ($this) {
            self::FULL => 'Only reserve if full quantity is available. Reject if insufficient.',
            self::PARTIAL => 'Reserve available quantity even if less than requested.',
            self::REJECT => 'Reject the reservation request if insufficient stock.',
            self::WAIT => 'Wait and retry when full stock becomes available. ⚠️ NOT YET IMPLEMENTED - Currently throws error. Future: Will queue and auto-retry when stock arrives.',
        };
    }

    /**
     * Check if partial reservation is allowed
     */
    public function allowsPartial(): bool
    {
        return $this === self::PARTIAL;
    }

    /**
     * Check if should reject on insufficient stock
     */
    public function shouldReject(): bool
    {
        return in_array($this, [self::FULL, self::REJECT]);
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
            fn(self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}
