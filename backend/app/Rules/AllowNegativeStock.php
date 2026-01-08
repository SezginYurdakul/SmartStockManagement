<?php

namespace App\Rules;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Contracts\Validation\Rule;

class AllowNegativeStock implements Rule
{
    protected int $productId;
    protected ?StockService $stockService;

    public function __construct(int $productId, ?StockService $stockService = null)
    {
        $this->productId = $productId;
        $this->stockService = $stockService ?? app(StockService::class);
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        $product = Product::find($this->productId);
        
        if (!$product) {
            return false;
        }
        
        $policy = $product->negative_stock_policy ?? 'NEVER';
        
        if ($policy === 'NEVER') {
            return $value >= 0;
        }
        
        if ($policy === 'LIMITED') {
            $currentStock = $this->getCurrentStock($product);
            $newStock = $currentStock - $value;
            $limit = $product->negative_stock_limit ?? 0;
            
            return $newStock >= -$limit;
        }
        
        return true; // ALLOWED
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $product = Product::find($this->productId);
        $policy = $product->negative_stock_policy ?? 'NEVER';
        
        if ($policy === 'NEVER') {
            return 'This product cannot go negative.';
        }
        
        if ($policy === 'LIMITED') {
            $limit = $product->negative_stock_limit ?? 0;
            return "This product can only go negative up to {$limit} units.";
        }
        
        return 'Invalid stock quantity.';
    }

    /**
     * Get current stock for product
     */
    protected function getCurrentStock(Product $product): float
    {
        $stock = $this->stockService->getProductStock($product->id, null);
        return $stock['quantity_available'] ?? 0;
    }
}
