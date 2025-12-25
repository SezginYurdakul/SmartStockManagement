<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Auth;

class CurrencyService
{
    /**
     * Cache TTL for exchange rates (in seconds)
     */
    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Get all active currencies
     */
    public function getActiveCurrencies()
    {
        return Cache::remember('currencies:active', $this->cacheTtl, function () {
            return Currency::active()->orderBy('code')->get();
        });
    }

    /**
     * Get a single currency by code
     */
    public function getCurrencyByCode(string $code): ?Currency
    {
        return Currency::where('code', $code)->first();
    }

        /**
     * Get a single currency by id
     */
    public function getCurrencyById(int $id): ?Currency
    {
        return Currency::where('code', $id)->first();
    }

    /**
     * Create a new currency
     */
    public function createCurrency(array $data): Currency
    {
        Log::info('Creating new currency', ['code' => $data['code']]);

        $currency = Currency::create($data);

        Cache::forget('currencies:active');

        return $currency;
    }

    /**
     * Update a currency
     */
    public function updateCurrency(Currency $currency, array $data): Currency
    {
        $currency->update($data);

        Cache::forget('currencies:active');

        return $currency->fresh();
    }

    /**
     * Delete a currency
     */
    public function deleteCurrency(Currency $currency): bool
    {
        // Check if currency has exchange rates or prices
        if ($currency->exchangeRatesFrom()->count() > 0 || $currency->exchangeRatesTo()->count() > 0) {
            throw new BusinessException("Cannot delete currency with existing exchange rates");
        }

        if ($currency->productPrices()->count() > 0) {
            throw new BusinessException("Cannot delete currency with existing product prices");
        }

        Cache::forget('currencies:active');

        return $currency->delete();
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $from, string $to, ?string $date = null): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $date = $date ?? now()->toDateString();
        $cacheKey = "exchange_rate:{$from}:{$to}:{$date}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($from, $to, $date) {
            $rate = ExchangeRate::getLatestRate($from, $to, $date);

            if ($rate) {
                return $rate->rate;
            }

            // Try inverse rate
            $inverseRate = ExchangeRate::getLatestRate($to, $from, $date);

            if ($inverseRate && $inverseRate->rate != 0) {
                return 1 / $inverseRate->rate;
            }

            return null;
        });
    }

    /**
     * Convert amount between currencies
     */
    public function convert(float $amount, string $from, string $to, ?string $date = null): ?float
    {
        $rate = $this->getExchangeRate($from, $to, $date);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Set exchange rate
     */
    public function setExchangeRate(string $from, string $to, float $rate, ?string $date = null, string $source = 'manual'): ExchangeRate
    {
        $date = $date ?? now()->toDateString();

        Log::info('Setting exchange rate', [
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'date' => $date,
        ]);

        DB::beginTransaction();

        try {
            $exchangeRate = ExchangeRate::updateOrCreate(
                [
                    'from_currency' => $from,
                    'to_currency' => $to,
                    'effective_date' => $date,
                ],
                [
                    'rate' => $rate,
                    'source' => $source,
                    'created_by' => Auth::id(),
                ]
            );

            // Also create/update inverse rate
            if ($rate != 0) {
                ExchangeRate::updateOrCreate(
                    [
                        'from_currency' => $to,
                        'to_currency' => $from,
                        'effective_date' => $date,
                    ],
                    [
                        'rate' => 1 / $rate,
                        'source' => $source,
                        'created_by' => Auth::id(),
                    ]
                );
            }

            DB::commit();

            // Clear cache
            Cache::forget("exchange_rate:{$from}:{$to}:{$date}");
            Cache::forget("exchange_rate:{$to}:{$from}:{$date}");

            return $exchangeRate;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to set exchange rate', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get exchange rate history
     */
    public function getExchangeRateHistory(string $from, string $to, int $days = 30)
    {
        $startDate = now()->subDays($days)->toDateString();

        return ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('effective_date', '>=', $startDate)
            ->orderBy('effective_date', 'desc')
            ->get();
    }

    /**
     * Format amount in a currency
     */
    public function format(float $amount, string $currencyCode): string
    {
        $currency = $this->getCurrencyByCode($currencyCode);

        if (!$currency) {
            return number_format($amount, 2) . ' ' . $currencyCode;
        }

        return $currency->format($amount);
    }
}
