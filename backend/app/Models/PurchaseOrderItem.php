<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'line_number',
        'description',
        'quantity_ordered',
        'quantity_received',
        'quantity_cancelled',
        'uom_id',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'line_total',
        'expected_delivery_date',
        'actual_delivery_date',
        'lot_number',
        'notes',
        'over_delivery_tolerance_percentage',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'quantity_cancelled' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'over_delivery_tolerance_percentage' => 'decimal:2',
    ];

    /**
     * Purchase order relationship
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit of measure relationship
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingQuantityAttribute(): float
    {
        return $this->quantity_ordered - $this->quantity_received - $this->quantity_cancelled;
    }

    /**
     * Check if fully received
     */
    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    /**
     * Get receiving progress percentage
     */
    public function getReceivingProgressAttribute(): float
    {
        if ($this->quantity_ordered == 0) {
            return 0;
        }

        return round(($this->quantity_received / $this->quantity_ordered) * 100, 2);
    }

    /**
     * Calculate line total
     */
    public function calculateLineTotal(): void
    {
        $grossAmount = $this->quantity_ordered * $this->unit_price;

        // Apply discount
        if ($this->discount_percentage > 0) {
            $this->discount_amount = $grossAmount * ($this->discount_percentage / 100);
        }
        $netAmount = $grossAmount - $this->discount_amount;

        // Apply tax
        if ($this->tax_percentage > 0) {
            $this->tax_amount = $netAmount * ($this->tax_percentage / 100);
        }

        $this->line_total = $netAmount + $this->tax_amount;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateLineTotal();
        });

        static::saved(function ($item) {
            // Recalculate PO totals
            $item->purchaseOrder->calculateTotals();
            $item->purchaseOrder->save();
        });

        static::deleted(function ($item) {
            // Recalculate PO totals
            $item->purchaseOrder->calculateTotals();
            $item->purchaseOrder->save();
        });
    }
}
