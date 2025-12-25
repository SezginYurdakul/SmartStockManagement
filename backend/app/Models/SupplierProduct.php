<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SupplierProduct extends Pivot
{
    protected $table = 'supplier_products';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'unit_price',
        'currency',
        'minimum_order_qty',
        'lead_time_days',
        'is_preferred',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'minimum_order_qty' => 'decimal:3',
        'lead_time_days' => 'integer',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Supplier relationship
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
