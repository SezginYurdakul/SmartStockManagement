<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'thousands_separator',
        'decimal_separator',
        'symbol_first',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'symbol_first' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get exchange rates from this currency
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency', 'code');
    }

    /**
     * Get exchange rates to this currency
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency', 'code');
    }

    /**
     * Get product prices in this currency
     */
    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'currency_code', 'code');
    }

    /**
     * Format an amount in this currency
     */
    public function format(float $amount): string
    {
        $formatted = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        if ($this->symbol_first) {
            return $this->symbol . $formatted;
        }

        return $formatted . $this->symbol;
    }

    /**
     * Get the latest exchange rate to another currency
     */
    public function getExchangeRateTo(string $toCurrency, ?string $date = null): ?float
    {
        $date = $date ?? now()->toDateString();

        $rate = ExchangeRate::where('from_currency', $this->code)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        return $rate?->rate;
    }

    /**
     * Convert an amount to another currency
     */
    public function convertTo(float $amount, string $toCurrency, ?string $date = null): ?float
    {
        if ($this->code === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRateTo($toCurrency, $date);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Scope: Get only active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the route key for the model (use code for URL binding)
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
