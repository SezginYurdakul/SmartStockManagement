<?php

namespace App\Enums;

/**
 * NCR Disposition Enum
 *
 * Determines the final disposition of non-conforming material.
 * UI labels are stored in the lookup_values table.
 */
enum NcrDisposition: string
{
    case PENDING = 'pending';
    case USE_AS_IS = 'use_as_is';
    case REWORK = 'rework';
    case SCRAP = 'scrap';
    case RETURN_TO_SUPPLIER = 'return_to_supplier';
    case SORT_AND_USE = 'sort_and_use';
    case REJECT = 'reject';

    /**
     * Check if stock can be used after disposition
     */
    public function allowsStockUsage(): bool
    {
        return in_array($this, [
            self::USE_AS_IS,
            self::REWORK,
            self::SORT_AND_USE,
        ]);
    }

    /**
     * Check if requires manager/engineering approval
     */
    public function requiresApproval(): bool
    {
        return in_array($this, [
            self::USE_AS_IS,
            self::REWORK,
            self::SORT_AND_USE,
        ]);
    }

    /**
     * Check if disposition affects supplier metrics
     */
    public function affectsSupplierScore(): bool
    {
        return in_array($this, [
            self::SCRAP,
            self::RETURN_TO_SUPPLIER,
            self::REJECT,
        ]);
    }

    /**
     * Check if triggers cost tracking
     */
    public function tracksCost(): bool
    {
        return in_array($this, [
            self::REWORK,
            self::SCRAP,
            self::SORT_AND_USE,
        ]);
    }

    /**
     * Check if supplier should be notified
     */
    public function notifySupplier(): bool
    {
        return in_array($this, [
            self::RETURN_TO_SUPPLIER,
            self::REJECT,
            self::SCRAP,
        ]);
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
            self::USE_AS_IS => 'Use As Is',
            self::REWORK => 'Rework',
            self::SCRAP => 'Scrap',
            self::RETURN_TO_SUPPLIER => 'Return to Supplier',
            self::SORT_AND_USE => 'Sort and Use',
            self::REJECT => 'Reject',
        };
    }
}
