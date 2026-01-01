<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bom_id',
        'component_id',
        'line_number',
        'quantity',
        'uom_id',
        'scrap_percentage',
        'is_optional',
        'is_phantom',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'scrap_percentage' => 'decimal:2',
        'is_optional' => 'boolean',
        'is_phantom' => 'boolean',
    ];

    /**
     * Parent BOM
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * Component product
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    /**
     * Unit of measure
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Calculate required quantity including scrap
     */
    public function getRequiredQuantity(float $parentQuantity = 1): float
    {
        $scrapFactor = 1 + ($this->scrap_percentage / 100);
        return $this->quantity * $parentQuantity * $scrapFactor;
    }

    /**
     * Get the component's default BOM (for phantom explosion)
     */
    public function getComponentDefaultBom(): ?Bom
    {
        if (!$this->is_phantom) {
            return null;
        }

        return Bom::where('product_id', $this->component_id)
            ->where('is_default', true)
            ->active()
            ->first();
    }

    /**
     * Check if this component can be exploded (has its own BOM)
     */
    public function canExplode(): bool
    {
        return $this->is_phantom && $this->getComponentDefaultBom() !== null;
    }

    /**
     * Scope: Required items only (non-optional)
     */
    public function scopeRequired($query)
    {
        return $query->where('is_optional', false);
    }

    /**
     * Scope: Phantom items only
     */
    public function scopePhantom($query)
    {
        return $query->where('is_phantom', true);
    }
}
