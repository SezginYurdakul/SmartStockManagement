<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'line_number',
        'description',
        'quantity_ordered',
        'quantity_shipped',
        'quantity_cancelled',
        'uom_id',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'line_total',
        'notes',
        'over_delivery_tolerance_percentage',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:4',
        'quantity_shipped' => 'decimal:4',
        'quantity_cancelled' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:2',
        'over_delivery_tolerance_percentage' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateLineTotal();
        });

        static::saved(function ($item) {
            $item->salesOrder->calculateTotals();
            $item->salesOrder->save();
        });

        static::deleted(function ($item) {
            $item->salesOrder->calculateTotals();
            $item->salesOrder->save();
        });
    }

    /**
     * Sales order relationship
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Delivery note items for this SO item
     */
    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    /**
     * Get remaining quantity to ship
     */
    public function getRemainingQuantityAttribute(): float
    {
        return $this->quantity_ordered - $this->quantity_shipped - $this->quantity_cancelled;
    }

    /**
     * Check if fully shipped
     */
    public function getIsFullyShippedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    /**
     * Get shipping progress percentage
     */
    public function getShippingProgressAttribute(): float
    {
        if ($this->quantity_ordered <= 0) {
            return 0;
        }

        return round(($this->quantity_shipped / $this->quantity_ordered) * 100, 2);
    }

    /**
     * Calculate line total
     */
    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity_ordered * $this->unit_price;

        // Apply discount
        if ($this->discount_percentage > 0) {
            $this->discount_amount = $subtotal * ($this->discount_percentage / 100);
        }
        $subtotal -= $this->discount_amount;

        // Calculate tax
        if ($this->tax_percentage > 0) {
            $this->tax_amount = $subtotal * ($this->tax_percentage / 100);
        }

        $this->line_total = $subtotal + $this->tax_amount;
    }
}
