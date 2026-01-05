<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MRP Cache Service
 * 
 * Redis-based caching layer for MRP calculations to improve performance
 * with large product lists and deep BOM hierarchies.
 */
class MrpCacheService
{
    protected const CACHE_PREFIX = 'mrp:';
    protected const CACHE_TTL = 14400; // 4 hours (increased from 1 hour for safety)
    protected const LOCK_TTL = 10800; // 3 hours (increased from 5 minutes for long-running MRP)
    protected const CHUNK_SIZE = 100; // Process 100 products at a time
    protected const DIRTY_PRODUCTS_TTL = 86400; // 24 hours for dirty products tracking

    /**
     * Get cache key for Low-Level Code calculation
     */
    protected function getLowLevelCodeCacheKey(int $companyId): string
    {
        return self::CACHE_PREFIX . "llc:company:{$companyId}";
    }

    /**
     * Get cache key for BOM structure
     */
    protected function getBomCacheKey(int $bomId): string
    {
        return self::CACHE_PREFIX . "bom:{$bomId}";
    }

    /**
     * Get cache key for product BOM explosion
     */
    protected function getBomExplosionCacheKey(int $productId, float $quantity): string
    {
        $qtyHash = md5((string) $quantity);
        return self::CACHE_PREFIX . "explosion:product:{$productId}:qty:{$qtyHash}";
    }

    /**
     * Get cache key for MRP run progress
     */
    protected function getProgressCacheKey(int $runId): string
    {
        return self::CACHE_PREFIX . "progress:run:{$runId}";
    }

    /**
     * Get cache key for distributed lock
     */
    protected function getLockCacheKey(int $companyId): string
    {
        return self::CACHE_PREFIX . "lock:company:{$companyId}";
    }

    /**
     * Get cached Low-Level Codes
     */
    public function getCachedLowLevelCodes(int $companyId): ?array
    {
        $cacheKey = $this->getLowLevelCodeCacheKey($companyId);
        $cached = Redis::get($cacheKey);
        
        return $cached ? json_decode($cached, true) : null;
    }

    /**
     * Cache Low-Level Codes
     */
    public function cacheLowLevelCodes(int $companyId, array $codes): void
    {
        $cacheKey = $this->getLowLevelCodeCacheKey($companyId);
        Redis::setex($cacheKey, self::CACHE_TTL, json_encode($codes));
    }

    /**
     * Invalidate Low-Level Code cache
     */
    public function invalidateLowLevelCodes(int $companyId): void
    {
        $cacheKey = $this->getLowLevelCodeCacheKey($companyId);
        Redis::del($cacheKey);
    }

    /**
     * Get cached BOM structure
     */
    public function getCachedBomStructure(int $bomId): ?array
    {
        $cacheKey = $this->getBomCacheKey($bomId);
        $cached = Redis::get($cacheKey);
        
        return $cached ? json_decode($cached, true) : null;
    }

    /**
     * Cache BOM structure
     */
    public function cacheBomStructure(int $bomId, array $structure): void
    {
        $cacheKey = $this->getBomCacheKey($bomId);
        Redis::setex($cacheKey, self::CACHE_TTL, json_encode($structure));
    }

    /**
     * Get cached BOM explosion result
     */
    public function getCachedBomExplosion(int $productId, float $quantity): ?array
    {
        $cacheKey = $this->getBomExplosionCacheKey($productId, $quantity);
        $cached = Redis::get($cacheKey);
        
        return $cached ? json_decode($cached, true) : null;
    }

    /**
     * Cache BOM explosion result
     */
    public function cacheBomExplosion(int $productId, float $quantity, array $explosion): void
    {
        $cacheKey = $this->getBomExplosionCacheKey($productId, $quantity);
        Redis::setex($cacheKey, self::CACHE_TTL, json_encode($explosion));
    }

    /**
     * Update MRP run progress
     */
    public function updateProgress(int $runId, int $processed, int $total, ?string $currentProduct = null): void
    {
        $cacheKey = $this->getProgressCacheKey($runId);
        $progress = [
            'processed' => $processed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
            'current_product' => $currentProduct,
            'updated_at' => now()->toIso8601String(),
        ];
        
        Redis::setex($cacheKey, self::LOCK_TTL, json_encode($progress));
    }

    /**
     * Get MRP run progress
     */
    public function getProgress(int $runId): ?array
    {
        $cacheKey = $this->getProgressCacheKey($runId);
        $cached = Redis::get($cacheKey);
        
        return $cached ? json_decode($cached, true) : null;
    }

    /**
     * Clear progress cache
     */
    public function clearProgress(int $runId): void
    {
        $cacheKey = $this->getProgressCacheKey($runId);
        Redis::del($cacheKey);
    }

    /**
     * Acquire distributed lock for MRP run
     */
    public function acquireLock(int $companyId, int $runId, int $ttl = null): bool
    {
        $lockKey = $this->getLockCacheKey($companyId);
        $lockValue = "run:{$runId}:" . uniqid();
        $ttl = $ttl ?? self::LOCK_TTL;

        // Try to set lock with expiration
        $result = Redis::set($lockKey, $lockValue, 'EX', $ttl, 'NX');
        
        return $result === true;
    }

    /**
     * Release distributed lock
     */
    public function releaseLock(int $companyId, int $runId): void
    {
        $lockKey = $this->getLockCacheKey($companyId);
        $lockValue = Redis::get($lockKey);
        
        // Only release if we own the lock
        if ($lockValue && str_contains($lockValue, "run:{$runId}")) {
            Redis::del($lockKey);
        }
    }

    /**
     * Check if lock exists
     */
    public function hasLock(int $companyId): bool
    {
        $lockKey = $this->getLockCacheKey($companyId);
        return Redis::exists($lockKey) > 0;
    }

    /**
     * Get lock information
     */
    public function getLockInfo(int $companyId): ?array
    {
        $lockKey = $this->getLockCacheKey($companyId);
        $lockValue = Redis::get($lockKey);
        $ttl = Redis::ttl($lockKey);
        
        if (!$lockValue) {
            return null;
        }

        return [
            'value' => $lockValue,
            'ttl' => $ttl,
        ];
    }

    /**
     * Invalidate all MRP caches for a company
     */
    public function invalidateCompanyCache(int $companyId): void
    {
        $pattern = self::CACHE_PREFIX . "*:company:{$companyId}*";
        $keys = Redis::keys($pattern);
        
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Get chunk size for processing
     */
    public function getChunkSize(): int
    {
        return self::CHUNK_SIZE;
    }

    /**
     * Store pre-loaded data in Redis (for large datasets)
     */
    public function storePreloadedData(int $runId, string $type, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . "preload:run:{$runId}:{$type}";
        
        // Use Redis hash for better memory efficiency
        foreach ($data as $key => $value) {
            Redis::hset($cacheKey, $key, json_encode($value));
        }
        
        Redis::expire($cacheKey, self::LOCK_TTL);
    }

    /**
     * Get pre-loaded data from Redis
     */
    public function getPreloadedData(int $runId, string $type, string $key): ?array
    {
        $cacheKey = self::CACHE_PREFIX . "preload:run:{$runId}:{$type}";
        $cached = Redis::hget($cacheKey, $key);
        
        return $cached ? json_decode($cached, true) : null;
    }

    /**
     * Clear pre-loaded data
     */
    public function clearPreloadedData(int $runId): void
    {
        $pattern = self::CACHE_PREFIX . "preload:run:{$runId}:*";
        $keys = Redis::keys($pattern);
        
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    /**
     * Get cache key for dirty products set
     */
    protected function getDirtyProductsCacheKey(int $companyId): string
    {
        return self::CACHE_PREFIX . "dirty:company:{$companyId}";
    }

    /**
     * Mark a product as dirty (changed and needs MRP recalculation)
     */
    public function markProductDirty(int $companyId, int $productId): void
    {
        $cacheKey = $this->getDirtyProductsCacheKey($companyId);
        Redis::sadd($cacheKey, $productId);
        Redis::expire($cacheKey, self::DIRTY_PRODUCTS_TTL);
    }

    /**
     * Mark multiple products as dirty
     */
    public function markProductsDirty(int $companyId, array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $cacheKey = $this->getDirtyProductsCacheKey($companyId);
        Redis::sadd($cacheKey, ...$productIds);
        Redis::expire($cacheKey, self::DIRTY_PRODUCTS_TTL);
    }

    /**
     * Get all dirty products for a company
     */
    public function getDirtyProducts(int $companyId): array
    {
        $cacheKey = $this->getDirtyProductsCacheKey($companyId);
        $productIds = Redis::smembers($cacheKey);
        
        return array_map('intval', $productIds ?: []);
    }

    /**
     * Clear dirty products list (after MRP run)
     */
    public function clearDirtyProducts(int $companyId): void
    {
        $cacheKey = $this->getDirtyProductsCacheKey($companyId);
        Redis::del($cacheKey);
    }

    /**
     * Check if incremental MRP should be used
     */
    public function shouldUseIncremental(int $companyId): bool
    {
        $dirtyCount = count($this->getDirtyProducts($companyId));
        
        // Use incremental if less than 20% of products are dirty
        $totalProducts = \App\Models\Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();
        
        if ($totalProducts === 0) {
            return false;
        }
        
        $dirtyPercentage = ($dirtyCount / $totalProducts) * 100;
        return $dirtyPercentage < 20 && $dirtyCount > 0;
    }
}
