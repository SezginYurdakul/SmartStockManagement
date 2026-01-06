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
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::CONFIRMED, self::SHIPPED, self::CANCELLED],
            self::CONFIRMED => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to target status is allowed
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if can be edited
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT || $this === self::CONFIRMED;
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
            self::CONFIRMED => 'Confirmed',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get color for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::CONFIRMED => 'blue',
            self::SHIPPED => 'cyan',
            self::DELIVERED => 'green',
            self::CANCELLED => 'red',
        };
    }
}
