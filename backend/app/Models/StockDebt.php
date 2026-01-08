<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockDebt extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'stock_movement_id',
        'quantity',
        'reconciled_quantity',
        'reference_type',
        'reference_id',
        'reconciled_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reconciled_quantity' => 'decimal:3',
        'outstanding_quantity' => 'decimal:3',
        'reconciled_at' => 'datetime',
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
     * Get the stock movement that created this debt
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the reference (polymorphic)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if debt is fully reconciled
     */
    public function isFullyReconciled(): bool
    {
        return $this->reconciled_quantity >= $this->quantity;
    }

    /**
     * Scope: Outstanding debts
     */
    public function scopeOutstanding($query)
    {
        return $query->whereColumn('reconciled_quantity', '<', 'quantity');
    }

    /**
     * Scope: Fully reconciled
     */
    public function scopeReconciled($query)
    {
        return $query->whereColumn('reconciled_quantity', '>=', 'quantity');
    }
}
