<?php

namespace App\Enums;

/**
 * Work Order Status Enum
 *
 * Manages Work Order lifecycle states and transitions.
 */
enum WorkOrderStatus: string
{
    case DRAFT = 'draft';
    case RELEASED = 'released';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case ON_HOLD = 'on_hold';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::RELEASED => 'Released',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::ON_HOLD => 'On Hold',
        };
    }

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::RELEASED, self::CANCELLED],
            self::RELEASED => [self::IN_PROGRESS, self::ON_HOLD, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::ON_HOLD, self::CANCELLED],
            self::ON_HOLD => [self::RELEASED, self::IN_PROGRESS, self::CANCELLED],
            self::COMPLETED => [], // Final state
            self::CANCELLED => [], // Final state
        };
    }

    /**
     * Check if transition to target status is allowed
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions());
    }

    /**
     * Check if work order can be edited
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if work order can be released
     */
    public function canRelease(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if work order can be started
     */
    public function canStart(): bool
    {
        return in_array($this, [self::RELEASED, self::ON_HOLD]);
    }

    /**
     * Check if work order can be completed
     */
    public function canComplete(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if work order can be cancelled
     */
    public function canCancel(): bool
    {
        return !$this->isFinal();
    }

    /**
     * Check if work order can be put on hold
     */
    public function canHold(): bool
    {
        return in_array($this, [self::RELEASED, self::IN_PROGRESS]);
    }

    /**
     * Check if work order can issue materials
     */
    public function canIssueMaterials(): bool
    {
        return in_array($this, [self::RELEASED, self::IN_PROGRESS]);
    }

    /**
     * Check if work order can receive finished goods
     */
    public function canReceiveFinishedGoods(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if work order is in final state
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }

    /**
     * Check if work order is active
     */
    public function isActive(): bool
    {
        return in_array($this, [self::RELEASED, self::IN_PROGRESS]);
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
