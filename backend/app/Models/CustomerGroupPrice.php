<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGroupPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_group_id',
        'product_id',
        'price',
        'min_quantity',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'min_quantity' => 'decimal:4',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /**
     * Customer group relationship
     */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: Valid prices (within date range)
     */
    public function scopeValid($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $date);
        });
    }

    /**
     * Scope: For specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: For minimum quantity
     */
    public function scopeForQuantity($query, float $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity');
    }
}
