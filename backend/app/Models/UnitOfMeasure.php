<?php

namespace App\Models;

use App\Enums\UomType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use BelongsToCompany;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'uom_type',
        'base_unit_id',
        'conversion_factor',
        'precision',
        'is_active',
    ];

    protected $casts = [
        'uom_type' => UomType::class,
        'conversion_factor' => 'decimal:6',
        'precision' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the base unit for conversion
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    /**
     * Get derived units (units that use this as base)
     */
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(UnitOfMeasure::class, 'base_unit_id');
    }

    /**
     * Get all products using this unit
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'uom_id');
    }

    /**
     * Check if this is a base unit (has no base_unit_id)
     */
    public function isBaseUnit(): bool
    {
        return $this->base_unit_id === null;
    }

    /**
     * Convert a quantity from this unit to another unit
     */
    public function convertTo(float $quantity, UnitOfMeasure $targetUnit): ?float
    {
        // Same unit, no conversion needed
        if ($this->id === $targetUnit->id) {
            return $quantity;
        }

        // Different types cannot be converted
        // Compare enum values since uom_type is now cast to UomType enum
        $thisType = $this->uom_type instanceof UomType ? $this->uom_type->value : $this->uom_type;
        $targetType = $targetUnit->uom_type instanceof UomType ? $targetUnit->uom_type->value : $targetUnit->uom_type;

        if ($thisType !== $targetType) {
            return null;
        }

        // Convert to base unit first, then to target
        $baseQuantity = $this->toBaseUnit($quantity);

        if ($baseQuantity === null) {
            return null;
        }

        return $targetUnit->fromBaseUnit($baseQuantity);
    }

    /**
     * Convert quantity to base unit
     */
    public function toBaseUnit(float $quantity): ?float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }

        if ($this->conversion_factor === null) {
            return null;
        }

        return $quantity * $this->conversion_factor;
    }

    /**
     * Convert quantity from base unit
     */
    public function fromBaseUnit(float $quantity): ?float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }

        if ($this->conversion_factor === null || $this->conversion_factor == 0) {
            return null;
        }

        return $quantity / $this->conversion_factor;
    }

    /**
     * Format a quantity with this unit's precision
     */
    public function formatQuantity(float $quantity): string
    {
        return number_format($quantity, $this->precision);
    }

    /**
     * Scope: Get only active units
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get only base units
     */
    public function scopeBaseUnits($query)
    {
        return $query->whereNull('base_unit_id');
    }

    /**
     * Scope: Get by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('uom_type', $type);
    }
}
