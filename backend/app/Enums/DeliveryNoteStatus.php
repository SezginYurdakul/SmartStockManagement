<?php

namespace App\Enums;

/**
 * Delivery Note Status Enum
 *
 * Manages delivery note lifecycle states.
 */
enum DeliveryNoteStatus: string
{
    case DRAFT = 'draft';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if can be edited
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if can be cancelled
     */
    public function canCancel(): bool
    {
        return match ($this) {
            self::DRAFT, self::SHIPPED => true,
            default => false,
        };
    }

    /**
     * Check if is final state
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::CANCELLED => true,
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
     * Get label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }
}
