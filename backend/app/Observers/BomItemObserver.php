<?php

namespace App\Observers;

use App\Models\BomItem;
use App\Services\AuditLogService;
use App\Services\MrpCacheService;
use Illuminate\Support\Facades\Log;

/**
 * BOM Item Observer
 * Automatically invalidates BOM explode cache when BOM items are created, updated, or deleted
 * and logs audit events for compliance
 */
class BomItemObserver
{
    public function __construct(
        protected MrpCacheService $cacheService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Handle the BomItem "created" event.
     */
    public function created(BomItem $bomItem): void
    {
        $this->invalidateCache($bomItem);
        
        // Audit logging
        $bomNumber = $bomItem->bom?->bom_number ?? 'N/A';
        $componentName = $bomItem->component?->name ?? 'N/A';
        $this->auditLogService->logCreation(
            $bomItem,
            "BOM Item created: Component '{$componentName}' added to BOM {$bomNumber}"
        );
    }

    /**
     * Handle the BomItem "updated" event.
     */
    public function updated(BomItem $bomItem): void
    {
        // Invalidate if any structural field changed
        if ($bomItem->wasChanged(['component_id', 'quantity', 'scrap_percentage', 'is_phantom', 'is_optional'])) {
            $this->invalidateCache($bomItem);
        }
        
        // Audit logging
        $bomNumber = $bomItem->bom?->bom_number ?? 'N/A';
        $componentName = $bomItem->component?->name ?? 'N/A';
        $this->auditLogService->logUpdate(
            $bomItem,
            "BOM Item updated: Component '{$componentName}' in BOM {$bomNumber}"
        );
    }

    /**
     * Handle the BomItem "deleted" event.
     */
    public function deleted(BomItem $bomItem): void
    {
        $this->invalidateCache($bomItem);
        
        // Audit logging
        $bomNumber = $bomItem->bom?->bom_number ?? 'N/A';
        $componentName = $bomItem->component?->name ?? 'N/A';
        $this->auditLogService->logDeletion(
            $bomItem,
            "BOM Item deleted: Component '{$componentName}' removed from BOM {$bomNumber}"
        );
    }

    /**
     * Invalidate BOM explode cache
     */
    protected function invalidateCache(BomItem $bomItem): void
    {
        try {
            if ($bomItem->bom_id) {
                // Invalidate BOM explode cache for this BOM
                $this->cacheService->invalidateBomExplodeCache($bomItem->bom_id);
                
                // Also invalidate MRP cache (BOM structure changed)
                if ($bomItem->bom) {
                    $this->cacheService->invalidateLowLevelCodes($bomItem->bom->company_id);
                    $this->cacheService->invalidateCompanyCache($bomItem->bom->company_id);
                }
                
                Log::info('BOM explode cache invalidated due to BOM item change', [
                    'bom_item_id' => $bomItem->id,
                    'bom_id' => $bomItem->bom_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate BOM explode cache', [
                'bom_item_id' => $bomItem->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
