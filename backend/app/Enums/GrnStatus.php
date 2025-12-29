<?php

namespace App\Enums;

/**
 * Goods Received Note Status Enum
 *
 * Manages GRN lifecycle states and transitions.
 */
enum GrnStatus: string
{
    case DRAFT = 'draft';
    case PENDING_INSPECTION = 'pending_inspection';
    case INSPECTED = 'inspected';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_INSPECTION, self::COMPLETED, self::CANCELLED],
            self::PENDING_INSPECTION => [self::INSPECTED, self::CANCELLED],
            self::INSPECTED => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if GRN can be edited
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if GRN can be cancelled
     */
    public function canCancel(): bool
    {
        return match ($this) {
            self::DRAFT, self::PENDING_INSPECTION, self::INSPECTED => true,
            default => false,
        };
    }

    /**
     * Check if GRN can be deleted
     */
    public function canDelete(): bool
    {
        return match ($this) {
            self::DRAFT, self::CANCELLED => true,
            default => false,
        };
    }

    /**
     * Check if GRN requires inspection
     */
    public function requiresInspection(): bool
    {
        return $this === self::PENDING_INSPECTION;
    }

    /**
     * Check if GRN can add stock
     */
    public function canAddStock(): bool
    {
        return match ($this) {
            self::INSPECTED, self::COMPLETED => true,
            default => false,
        };
    }

    /**
     * Check if GRN is in final state
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELLED => true,
            default => false,
        };
    }

    /**
     * Check if GRN is active
     */
    public function isActive(): bool
    {
        return !$this->isFinal();
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get fallback label
     */
    public function fallbackLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_INSPECTION => 'Pending Inspection',
            self::INSPECTED => 'Inspected',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
