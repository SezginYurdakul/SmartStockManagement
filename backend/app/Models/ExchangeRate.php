<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
        'source',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
    ];

    /**
     * Get the source currency
     */
    public function fromCurrencyRelation(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency', 'code');
    }

    /**
     * Get the target currency
     */
    public function toCurrencyRelation(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency', 'code');
    }

    /**
     * Get the user who created this rate
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the inverse rate (to -> from)
     */
    public function getInverseRateAttribute(): ?float
    {
        if ($this->rate == 0) {
            return null;
        }

        return 1 / $this->rate;
    }

    /**
     * Convert an amount using this rate
     */
    public function convert(float $amount): float
    {
        return $amount * $this->rate;
    }

    /**
     * Scope: Get rates for a specific date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Scope: Get rates between two currencies
     */
    public function scopeBetween($query, string $from, string $to)
    {
        return $query->where('from_currency', $from)
            ->where('to_currency', $to);
    }

    /**
     * Get the latest rate between two currencies
     */
    public static function getLatestRate(string $from, string $to, ?string $date = null): ?self
    {
        $date = $date ?? now()->toDateString();

        return static::between($from, $to)
            ->forDate($date)
            ->first();
    }
}
