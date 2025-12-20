<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToCompany;

    /**
     * Movement types
     */
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_PRODUCTION_CONSUME = 'production_consume';
    public const TYPE_PRODUCTION_OUTPUT = 'production_output';
    public const TYPE_RETURN = 'return';
    public const TYPE_SCRAP = 'scrap';

    /**
     * Transaction types
     */
    public const TRANS_PURCHASE_ORDER = 'purchase_order';
    public const TRANS_SALES_ORDER = 'sales_order';
    public const TRANS_PRODUCTION_ORDER = 'production_order';
    public const TRANS_TRANSFER_ORDER = 'transfer_order';
    public const TRANS_ADJUSTMENT = 'adjustment';
    public const TRANS_INITIAL_STOCK = 'initial_stock';
    public const TRANS_RETURN = 'return';
    public const TRANS_SCRAP = 'scrap';

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'lot_number',
        'serial_number',
        'movement_type',
        'transaction_type',
        'reference_number',
        'reference_type',
        'reference_id',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'notes',
        'meta_data',
        'created_by',
        'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'meta_data' => 'array',
        'movement_date' => 'datetime',
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
     * Get the source warehouse (for transfers)
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * Get the destination warehouse (for transfers)
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Get the user who created the movement
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reference document (polymorphic)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by movement type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    /**
     * Scope to filter by transaction type
     */
    public function scopeOfTransactionType($query, string $type)
    {
        return $query->where('transaction_type', $type);
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
     * Scope to filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    /**
     * Scope for inbound movements
     */
    public function scopeInbound($query)
    {
        return $query->whereIn('movement_type', [
            self::TYPE_RECEIPT,
            self::TYPE_PRODUCTION_OUTPUT,
            self::TYPE_RETURN,
        ]);
    }

    /**
     * Scope for outbound movements
     */
    public function scopeOutbound($query)
    {
        return $query->whereIn('movement_type', [
            self::TYPE_ISSUE,
            self::TYPE_PRODUCTION_CONSUME,
            self::TYPE_SCRAP,
        ]);
    }

    /**
     * Check if this is an inbound movement
     */
    public function isInbound(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_RECEIPT,
            self::TYPE_PRODUCTION_OUTPUT,
            self::TYPE_RETURN,
        ]);
    }

    /**
     * Check if this is an outbound movement
     */
    public function isOutbound(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_ISSUE,
            self::TYPE_PRODUCTION_CONSUME,
            self::TYPE_SCRAP,
        ]);
    }

    /**
     * Get movement type label
     */
    public function getMovementTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            self::TYPE_RECEIPT => 'Receipt',
            self::TYPE_ISSUE => 'Issue',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_PRODUCTION_CONSUME => 'Production Consume',
            self::TYPE_PRODUCTION_OUTPUT => 'Production Output',
            self::TYPE_RETURN => 'Return',
            self::TYPE_SCRAP => 'Scrap',
            default => $this->movement_type,
        };
    }

    /**
     * Get transaction type label
     */
    public function getTransactionTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            self::TRANS_PURCHASE_ORDER => 'Purchase Order',
            self::TRANS_SALES_ORDER => 'Sales Order',
            self::TRANS_PRODUCTION_ORDER => 'Production Order',
            self::TRANS_TRANSFER_ORDER => 'Transfer Order',
            self::TRANS_ADJUSTMENT => 'Adjustment',
            self::TRANS_INITIAL_STOCK => 'Initial Stock',
            self::TRANS_RETURN => 'Return',
            self::TRANS_SCRAP => 'Scrap',
            default => $this->transaction_type,
        };
    }
}
