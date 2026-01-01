<?php

namespace App\Enums;

/**
 * Defect Type Enum
 *
 * Categorizes the type of quality defect found.
 * UI labels are stored in the lookup_values table.
 */
enum DefectType: string
{
    case DIMENSIONAL = 'dimensional';
    case VISUAL = 'visual';
    case FUNCTIONAL = 'functional';
    case DOCUMENTATION = 'documentation';
    case PACKAGING = 'packaging';
    case CONTAMINATION = 'contamination';
    case WRONG_ITEM = 'wrong_item';
    case QUANTITY_SHORT = 'quantity_short';
    case QUANTITY_OVER = 'quantity_over';
    case DAMAGE = 'damage';
    case OTHER = 'other';

    /**
     * Check if defect requires measurement data
     */
    public function requiresMeasurementData(): bool
    {
        return in_array($this, [
            self::DIMENSIONAL,
            self::FUNCTIONAL,
        ]);
    }

    /**
     * Check if defect requires photo evidence
     */
    public function requiresPhotoEvidence(): bool
    {
        return in_array($this, [
            self::VISUAL,
            self::PACKAGING,
            self::CONTAMINATION,
            self::DAMAGE,
        ]);
    }

    /**
     * Check if defect is supplier-related
     */
    public function isSupplierRelated(): bool
    {
        return in_array($this, [
            self::DIMENSIONAL,
            self::VISUAL,
            self::FUNCTIONAL,
            self::CONTAMINATION,
            self::WRONG_ITEM,
            self::QUANTITY_SHORT,
        ]);
    }

    /**
     * Check if defect is shipping-related
     */
    public function isShippingRelated(): bool
    {
        return in_array($this, [
            self::PACKAGING,
            self::DAMAGE,
            self::QUANTITY_SHORT,
        ]);
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
            self::DIMENSIONAL => 'Dimensional',
            self::VISUAL => 'Visual',
            self::FUNCTIONAL => 'Functional',
            self::DOCUMENTATION => 'Documentation',
            self::PACKAGING => 'Packaging',
            self::CONTAMINATION => 'Contamination',
            self::WRONG_ITEM => 'Wrong Item',
            self::QUANTITY_SHORT => 'Quantity Short',
            self::QUANTITY_OVER => 'Quantity Over',
            self::DAMAGE => 'Damage',
            self::OTHER => 'Other',
        };
    }
}
