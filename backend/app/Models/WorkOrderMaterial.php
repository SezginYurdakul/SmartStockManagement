<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'product_id',
        'bom_item_id',
        'quantity_required',
        'quantity_issued',
        'quantity_returned',
        'uom_id',
        'warehouse_id',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_required' => 'decimal:4',
        'quantity_issued' => 'decimal:4',
        'quantity_returned' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    /**
     * Parent work order
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Material product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Source BOM item (if from BOM)
     */
    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    /**
     * Unit of measure
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Source warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get outstanding quantity to issue
     */
    public function getOutstandingQuantityAttribute(): float
    {
        return max(0, $this->quantity_required - $this->quantity_issued + $this->quantity_returned);
    }

    /**
     * Get net issued quantity
     */
    public function getNetIssuedQuantityAttribute(): float
    {
        return $this->quantity_issued - $this->quantity_returned;
    }

    /**
     * Check if material is fully issued
     */
    public function isFullyIssued(): bool
    {
        return $this->outstanding_quantity <= 0;
    }

    /**
     * Check if there's a shortage
     */
    public function hasShortage(): bool
    {
        return $this->outstanding_quantity > 0;
    }

    /**
     * Scope: With outstanding quantities
     */
    public function scopeWithOutstanding($query)
    {
        return $query->whereRaw('quantity_required > (quantity_issued - quantity_returned)');
    }

    /**
     * Scope: Fully issued
     */
    public function scopeFullyIssued($query)
    {
        return $query->whereRaw('quantity_required <= (quantity_issued - quantity_returned)');
    }
}
