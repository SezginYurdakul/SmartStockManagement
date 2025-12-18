<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'currency_code',
        'price_type',
        'unit_price',
        'min_quantity',
        'customer_group_id',
        'effective_date',
        'expiry_date',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'min_quantity' => 'decimal:3',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Price type constants
     */
    public const TYPE_BASE = 'base';
    public const TYPE_COST = 'cost';
    public const TYPE_WHOLESALE = 'wholesale';
    public const TYPE_RETAIL = 'retail';
    public const TYPE_SPECIAL = 'special';

    public const TYPES = [
        self::TYPE_BASE => 'Base Price',
        self::TYPE_COST => 'Cost Price',
        self::TYPE_WHOLESALE => 'Wholesale Price',
        self::TYPE_RETAIL => 'Retail Price',
        self::TYPE_SPECIAL => 'Special Price',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the currency
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Check if this price is currently valid
     */
    public function isValid(?string $date = null): bool
    {
        $date = $date ?? now()->toDateString();

        if (!$this->is_active) {
            return false;
        }

        if ($this->effective_date > $date) {
            return false;
        }

        if ($this->expiry_date !== null && $this->expiry_date < $date) {
            return false;
        }

        return true;
    }

    /**
     * Format the price with currency
     */
    public function formatted(): string
    {
        $currency = $this->currency;

        if (!$currency) {
            return number_format($this->unit_price, 2) . ' ' . $this->currency_code;
        }

        return $currency->format($this->unit_price);
    }

    /**
     * Scope: Get active prices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get valid prices for a date
     */
    public function scopeValidOn($query, ?string $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $date);
            });
    }

    /**
     * Scope: Get prices by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('price_type', $type);
    }

    /**
     * Scope: Get prices for a currency
     */
    public function scopeInCurrency($query, string $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }

    /**
     * Scope: Get prices for a quantity (tiered pricing)
     */
    public function scopeForQuantity($query, float $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc');
    }
}
