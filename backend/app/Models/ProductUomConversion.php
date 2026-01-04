<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product-specific UOM Conversion
 *
 * Handles product-specific unit conversions that differ from standard conversions.
 *
 * Examples:
 * - 1 Box of M8 bolts = 500 pcs (but M10 bolts box = 250 pcs)
 * - 1 Pallet of small tires = 48 pcs (but large tires pallet = 24 pcs)
 * - 1 Drum of brand A oil = 200 L (but brand B = 208 L)
 */
class ProductUomConversion extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the source unit of measure
     */
    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    /**
     * Get the target unit of measure
     */
    public function toUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }

    /**
     * Convert quantity from source to target unit
     *
     * @param float $quantity Quantity in source unit
     * @return float Quantity in target unit
     */
    public function convert(float $quantity): float
    {
        return $quantity * $this->conversion_factor;
    }

    /**
     * Convert quantity from target back to source unit (reverse conversion)
     *
     * @param float $quantity Quantity in target unit
     * @return float|null Quantity in source unit, null if conversion factor is zero
     */
    public function reverseConvert(float $quantity): ?float
    {
        if ($this->conversion_factor == 0) {
            return null;
        }

        return $quantity / $this->conversion_factor;
    }

    /**
     * Get formatted conversion string for display
     * Example: "1 box = 500 pcs"
     */
    public function getDisplayString(): string
    {
        $fromCode = $this->fromUom?->code ?? '?';
        $toCode = $this->toUom?->code ?? '?';
        $factor = number_format($this->conversion_factor, $this->toUom?->precision ?? 2);

        return "1 {$fromCode} = {$factor} {$toCode}";
    }

    /**
     * Scope: Get only active conversions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get default conversions
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: Get conversions for a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Get conversions from a specific unit
     */
    public function scopeFromUnit($query, int $uomId)
    {
        return $query->where('from_uom_id', $uomId);
    }

    /**
     * Scope: Get conversions to a specific unit
     */
    public function scopeToUnit($query, int $uomId)
    {
        return $query->where('to_uom_id', $uomId);
    }
}
