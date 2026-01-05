<?php

namespace App\Observers;

use App\Models\Bom;
use App\Services\MrpCacheService;
use Illuminate\Support\Facades\Log;

/**
 * BOM Observer
 * Automatically invalidates MRP cache when BOMs are created, updated, or deleted
 */
class BomObserver
{
    public function __construct(
        protected MrpCacheService $cacheService
    ) {}

    /**
     * Handle the Bom "created" event.
     */
    public function created(Bom $bom): void
    {
        $this->invalidateCache($bom);
    }

    /**
     * Handle the Bom "updated" event.
     */
    public function updated(Bom $bom): void
    {
        // Invalidate if BOM structure or status changed
        if ($bom->wasChanged(['status', 'product_id', 'bom_type', 'version'])) {
            $this->invalidateCache($bom);
            
            // Mark parent product as dirty for incremental MRP
            if ($bom->product_id) {
                $this->markProductDirty($bom);
            }
        }
    }

    /**
     * Handle the Bom "deleted" event.
     */
    public function deleted(Bom $bom): void
    {
        $this->invalidateCache($bom);
    }

    /**
     * Invalidate MRP cache for the company
     */
    protected function invalidateCache(Bom $bom): void
    {
        try {
            // BOM changes always require LLC recalculation
            $this->cacheService->invalidateLowLevelCodes($bom->company_id);
            $this->cacheService->invalidateCompanyCache($bom->company_id);
            
            Log::info('MRP cache invalidated due to BOM change', [
                'bom_id' => $bom->id,
                'company_id' => $bom->company_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate MRP cache', [
                'bom_id' => $bom->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark product as dirty for incremental MRP
     */
    protected function markProductDirty(Bom $bom): void
    {
        try {
            if ($bom->product_id) {
                $this->cacheService->markProductDirty($bom->company_id, $bom->product_id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark product as dirty', [
                'bom_id' => $bom->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
