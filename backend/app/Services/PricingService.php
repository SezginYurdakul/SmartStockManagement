<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PricingService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get product price in a specific currency
     */
    public function getProductPrice(
        Product $product,
        string $currencyCode,
        string $priceType = 'base',
        float $quantity = 1,
        ?string $date = null
    ): ?array {
        $date = $date ?? now()->toDateString();

        // Try to get price directly in requested currency
        $price = $product->prices()
            ->active()
            ->validOn($date)
            ->inCurrency($currencyCode)
            ->ofType($priceType)
            ->forQuantity($quantity)
            ->first();

        if ($price) {
            return [
                'amount' => $price->unit_price,
                'currency' => $currencyCode,
                'formatted' => $this->currencyService->format($price->unit_price, $currencyCode),
                'is_converted' => false,
                'source_currency' => $currencyCode,
            ];
        }

        // If not found, try to convert from base currency
        $baseCurrency = auth()->user()?->company?->base_currency ?? 'USD';

        if ($currencyCode !== $baseCurrency) {
            $basePrice = $product->prices()
                ->active()
                ->validOn($date)
                ->inCurrency($baseCurrency)
                ->ofType($priceType)
                ->forQuantity($quantity)
                ->first();

            if ($basePrice) {
                $convertedAmount = $this->currencyService->convert(
                    $basePrice->unit_price,
                    $baseCurrency,
                    $currencyCode,
                    $date
                );

                if ($convertedAmount !== null) {
                    return [
                        'amount' => $convertedAmount,
                        'currency' => $currencyCode,
                        'formatted' => $this->currencyService->format($convertedAmount, $currencyCode),
                        'is_converted' => true,
                        'source_currency' => $baseCurrency,
                        'source_amount' => $basePrice->unit_price,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Set product price
     */
    public function setProductPrice(
        Product $product,
        string $currencyCode,
        float $unitPrice,
        string $priceType = 'base',
        ?string $effectiveDate = null,
        ?string $expiryDate = null,
        float $minQuantity = 1
    ): ProductPrice {
        $effectiveDate = $effectiveDate ?? now()->toDateString();

        Log::info('Setting product price', [
            'product_id' => $product->id,
            'currency' => $currencyCode,
            'price' => $unitPrice,
            'type' => $priceType,
        ]);

        DB::beginTransaction();

        try {
            // Deactivate existing prices of same type, currency, and min_quantity
            ProductPrice::where('product_id', $product->id)
                ->where('currency_code', $currencyCode)
                ->where('price_type', $priceType)
                ->where('min_quantity', $minQuantity)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Create new price
            $price = ProductPrice::create([
                'product_id' => $product->id,
                'currency_code' => $currencyCode,
                'price_type' => $priceType,
                'unit_price' => $unitPrice,
                'min_quantity' => $minQuantity,
                'effective_date' => $effectiveDate,
                'expiry_date' => $expiryDate,
                'is_active' => true,
            ]);

            DB::commit();

            Log::info('Product price set successfully', [
                'price_id' => $price->id,
            ]);

            return $price;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to set product price', ['error' => $e->getMessage()]);
            throw new BusinessException("Failed to set product price: {$e->getMessage()}");
        }
    }

    /**
     * Set multiple prices for a product
     */
    public function setProductPrices(Product $product, array $prices): array
    {
        $createdPrices = [];

        DB::beginTransaction();

        try {
            foreach ($prices as $priceData) {
                $createdPrices[] = $this->setProductPrice(
                    $product,
                    $priceData['currency_code'],
                    $priceData['unit_price'],
                    $priceData['price_type'] ?? 'base',
                    $priceData['effective_date'] ?? null,
                    $priceData['expiry_date'] ?? null,
                    $priceData['min_quantity'] ?? 1
                );
            }

            DB::commit();

            return $createdPrices;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all prices for a product
     */
    public function getProductPrices(Product $product, ?string $currencyCode = null): array
    {
        $query = $product->prices()->with('currency');

        if ($currencyCode) {
            $query->where('currency_code', $currencyCode);
        }

        return $query->orderBy('price_type')
            ->orderBy('currency_code')
            ->orderBy('min_quantity')
            ->get()
            ->toArray();
    }

    /**
     * Delete a product price
     */
    public function deleteProductPrice(ProductPrice $price): bool
    {
        Log::info('Deleting product price', ['price_id' => $price->id]);

        return $price->delete();
    }

    /**
     * Calculate tiered price for a quantity
     */
    public function calculateTieredPrice(
        Product $product,
        float $quantity,
        string $currencyCode,
        string $priceType = 'base'
    ): ?array {
        $price = $this->getProductPrice($product, $currencyCode, $priceType, $quantity);

        if (!$price) {
            return null;
        }

        return [
            'unit_price' => $price['amount'],
            'total_price' => $price['amount'] * $quantity,
            'quantity' => $quantity,
            'currency' => $currencyCode,
            'formatted_unit' => $price['formatted'],
            'formatted_total' => $this->currencyService->format($price['amount'] * $quantity, $currencyCode),
        ];
    }
}
