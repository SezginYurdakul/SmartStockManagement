<?php

namespace App\Enums;

/**
 * Operation Status Enum
 *
 * Manages work order operation states.
 */
enum OperationStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::SKIPPED => 'Skipped',
        };
    }

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::IN_PROGRESS, self::SKIPPED],
            self::IN_PROGRESS => [self::COMPLETED, self::SKIPPED],
            self::COMPLETED => [], // Final state
            self::SKIPPED => [], // Final state
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
     * Check if operation can be started
     */
    public function canStart(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if operation can be completed
     */
    public function canComplete(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if operation is in final state
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::SKIPPED]);
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
