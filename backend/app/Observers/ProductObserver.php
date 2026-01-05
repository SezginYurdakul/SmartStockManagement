<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\MrpCacheService;
use Illuminate\Support\Facades\Log;

/**
 * Product Observer
 * Automatically invalidates MRP cache when product MRP-related fields change
 */
class ProductObserver
{
    public function __construct(
        protected MrpCacheService $cacheService
    ) {}

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // Only invalidate if MRP-related fields changed
        $mrpFields = [
            'lead_time_days',
            'safety_stock',
            'reorder_point',
            'make_or_buy',
            'minimum_order_qty',
            'order_multiple',
            'maximum_stock',
        ];

        if ($product->wasChanged($mrpFields)) {
            $this->invalidateCache($product);
            
            // Mark product as dirty for incremental MRP
            $this->markProductDirty($product);
        }
    }

    /**
     * Invalidate MRP cache for the company
     */
    protected function invalidateCache(Product $product): void
    {
        try {
            // Invalidate LLC cache (product structure might have changed)
            $this->cacheService->invalidateLowLevelCodes($product->company_id);
            
            // Invalidate product-specific caches
            $this->cacheService->invalidateCompanyCache($product->company_id);
            
            Log::info('MRP cache invalidated due to product MRP field change', [
                'product_id' => $product->id,
                'company_id' => $product->company_id,
                'changed_fields' => array_intersect(
                    ['lead_time_days', 'safety_stock', 'reorder_point', 'make_or_buy'],
                    array_keys($product->getChanges())
                ),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate MRP cache', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark product as dirty for incremental MRP
     */
    protected function markProductDirty(Product $product): void
    {
        try {
            $this->cacheService->markProductDirty($product->company_id, $product->id);
        } catch (\Exception $e) {
            Log::error('Failed to mark product as dirty', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
