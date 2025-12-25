<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToCompany;

    /**
     * The table associated with the model.
     */
    protected $table = 'stock';

    /**
     * Stock statuses
     */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'lot_number',
        'serial_number',
        'quantity_on_hand',
        'quantity_reserved',
        'unit_cost',
        'expiry_date',
        'received_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'quantity_reserved' => 'decimal:3',
        'quantity_available' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'expiry_date' => 'date',
        'received_date' => 'date',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Scope to filter available stock
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
            ->where('quantity_available', '>', 0);
    }

    /**
     * Scope to filter by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by warehouse
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by lot number
     */
    public function scopeWithLot($query, string $lotNumber)
    {
        return $query->where('lot_number', $lotNumber);
    }

    /**
     * Scope to filter expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    /**
     * Scope to filter expired stock
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    /**
     * Scope to filter low stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->whereColumn('stock.quantity_available', '<=', 'products.low_stock_threshold');
        });
    }

    /**
     * Check if stock is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if stock is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }

    /**
     * Reserve stock quantity
     */
    public function reserve(float $quantity): bool
    {
        if ($this->quantity_available < $quantity) {
            return false;
        }

        $this->increment('quantity_reserved', $quantity);
        return true;
    }

    /**
     * Release reserved stock
     */
    public function releaseReservation(float $quantity): bool
    {
        if ($this->quantity_reserved < $quantity) {
            return false;
        }

        $this->decrement('quantity_reserved', $quantity);
        return true;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_QUARANTINE => 'Quarantine',
            self::STATUS_DAMAGED => 'Damaged',
            self::STATUS_EXPIRED => 'Expired',
            default => $this->status,
        };
    }
}
