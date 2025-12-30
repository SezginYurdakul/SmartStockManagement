<?php

namespace App\Enums;

/**
 * BOM Status Enum
 *
 * Manages BOM lifecycle states.
 */
enum BomStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case OBSOLETE = 'obsolete';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::OBSOLETE => 'Obsolete',
        };
    }

    /**
     * Get allowed status transitions
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::ACTIVE],
            self::ACTIVE => [self::OBSOLETE, self::DRAFT],
            self::OBSOLETE => [self::DRAFT], // Can reactivate by going back to draft
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
     * Check if BOM can be edited
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if BOM can be used for production
     */
    public function canUseForProduction(): bool
    {
        return $this === self::ACTIVE;
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
