<?php

namespace App\Enums;

/**
 * Inspection Disposition Enum
 *
 * Determines what happens to inspected goods.
 * UI labels are stored in the lookup_values table.
 */
enum InspectionDisposition: string
{
    case PENDING = 'pending';
    case ACCEPT = 'accept';
    case REJECT = 'reject';
    case REWORK = 'rework';
    case RETURN_TO_SUPPLIER = 'return_to_supplier';
    case USE_AS_IS = 'use_as_is';

    /**
     * Check if stock can be added to inventory
     */
    public function allowsStockEntry(): bool
    {
        return match ($this) {
            self::ACCEPT, self::USE_AS_IS => true,
            default => false,
        };
    }

    /**
     * Check if requires manager approval
     */
    public function requiresApproval(): bool
    {
        return match ($this) {
            self::USE_AS_IS, self::REWORK => true,
            default => false,
        };
    }

    /**
     * Check if triggers supplier notification
     */
    public function notifySupplier(): bool
    {
        return match ($this) {
            self::REJECT, self::RETURN_TO_SUPPLIER => true,
            default => false,
        };
    }

    /**
     * Check if disposition is final
     */
    public function isFinal(): bool
    {
        return $this !== self::PENDING;
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
            self::PENDING => 'Pending Decision',
            self::ACCEPT => 'Accept',
            self::REJECT => 'Reject',
            self::REWORK => 'Rework',
            self::RETURN_TO_SUPPLIER => 'Return to Supplier',
            self::USE_AS_IS => 'Use As Is',
        };
    }
}
