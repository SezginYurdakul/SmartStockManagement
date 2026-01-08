<?php

namespace App\Services;

use App\Models\MrpRun;
use App\Models\MrpRecommendation;
use App\Models\Product;
use App\Models\Stock;
use App\Models\WorkOrder;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Bom;
use App\Models\Setting;
use App\Models\CompanyCalendar;
use App\Enums\MrpRunStatus;
use App\Enums\MrpRecommendationType;
use App\Enums\MrpRecommendationStatus;
use App\Enums\MrpPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\PoStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MrpService
{
    protected int $companyId;
    protected MrpRun $currentRun;
    protected array $warnings = [];
    protected array $warningsSummary = []; // Grouped warnings by type
    protected int $recommendationsGenerated = 0;
    
    // Pre-loaded data to avoid N+1 queries
    protected Collection $preloadedStocks;
    protected Collection $preloadedSalesDemands;
    protected Collection $preloadedPoReceipts;
    protected Collection $preloadedWoDemands;
    protected Collection $preloadedWoReceipts;

    public function __construct(
        protected BomService $bomService,
        protected StockService $stockService,
        protected MrpCacheService $cacheService
    ) {}

    // =========================================
    // MRP Run Management
    // =========================================

    /**
     * Get paginated MRP runs
     */
    public function getRuns(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MrpRun::with(['creator:id,first_name,last_name'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->status(MrpRunStatus::from($filters['status']));
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a specific MRP run with recommendations
     */
    public function getRun(MrpRun $run): MrpRun
    {
        $run = $run->load([
            'creator:id,first_name,last_name',
            'recommendations.product:id,name,sku',
            'recommendations.warehouse:id,name,code',
        ]);

        // Add progress information if run is in progress
        if ($run->status === MrpRunStatus::RUNNING) {
            $progress = $this->cacheService->getProgress($run->id);
            if ($progress) {
                $run->setAttribute('progress', $progress);
            }
        }

        return $run;
    }

    /**
     * Get MRP run progress
     */
    public function getRunProgress(MrpRun $run): ?array
    {
        if ($run->status !== MrpRunStatus::RUNNING) {
            return null;
        }

        return $this->cacheService->getProgress($run->id);
    }

    /**
     * Invalidate MRP cache for a company
     * Call this when BOMs or product structures change
     */
    public function invalidateCache(?int $companyId = null): void
    {
        $companyId = $companyId ?? Auth::user()->company_id;
        $this->cacheService->invalidateCompanyCache($companyId);
        $this->cacheService->invalidateLowLevelCodes($companyId);
        
        Log::info('MRP cache invalidated', ['company_id' => $companyId]);
    }

    /**
     * Create and execute an MRP run
     * 
     * @param array $params MRP run parameters
     * @param bool $async Whether to run in background (queue) or synchronously
     * @return MrpRun
     */
    public function runMrp(array $params, bool $async = null): MrpRun
    {
        $this->companyId = Auth::user()->company_id;
        $this->warnings = [];
        $this->warningsSummary = [];
        $this->recommendationsGenerated = 0;

        // Auto-detect if async should be used based on product count
        if ($async === null) {
            $async = $this->shouldUseAsync($params);
        }

        Log::info('Starting MRP run', array_merge($params, ['async' => $async]));

        // Create run record first
        $run = DB::transaction(function () use ($params, $async) {
            // Create run record
            $this->currentRun = MrpRun::create([
                'company_id' => $this->companyId,
                'run_number' => MrpRun::generateRunNumber($this->companyId),
                'name' => $params['name'] ?? null,
                'planning_horizon_start' => $params['planning_horizon_start'] ?? today(),
                'planning_horizon_end' => $params['planning_horizon_end'] ?? today()->addDays(30),
                'include_safety_stock' => $params['include_safety_stock'] ?? true,
                'respect_lead_times' => $params['respect_lead_times'] ?? true,
                'consider_wip' => $params['consider_wip'] ?? true,
                'net_change' => $params['net_change'] ?? false,
                'product_filters' => $params['product_filters'] ?? null,
                'warehouse_filters' => $params['warehouse_filters'] ?? null,
                'status' => MrpRunStatus::PENDING,
                'created_by' => Auth::id(),
            ]);

            try {
                // Validate critical data before starting
                $this->validateMrpRunData();

                // Acquire distributed lock to prevent concurrent runs
                if (!$this->cacheService->acquireLock($this->companyId, $this->currentRun->id)) {
                    throw new \Exception('Another MRP run is already in progress for this company.');
                }

                $this->currentRun->markAsRunning();

                // Execute MRP calculation
                $productsProcessed = $this->executeMrpCalculation();

                // Log warnings if any
                $totalWarnings = $this->getTotalWarningsCount();
                if ($totalWarnings > 0) {
                    Log::warning('MRP run completed with warnings', [
                        'run_id' => $this->currentRun->id,
                        'warnings_summary' => $this->warningsSummary,
                        'total_warnings' => $totalWarnings,
                    ]);
                }

                $this->currentRun->markAsCompleted(
                    $productsProcessed,
                    $this->recommendationsGenerated,
                    $totalWarnings,
                    $this->getWarningsSummary()
                );

                // Clear progress cache
                $this->cacheService->clearProgress($this->currentRun->id);
                $this->cacheService->clearPreloadedData($this->currentRun->id);

                Log::info('MRP run completed', [
                    'run_id' => $this->currentRun->id,
                    'products_processed' => $productsProcessed,
                    'recommendations' => $this->recommendationsGenerated,
                    'warnings_count' => $totalWarnings,
                ]);
            } catch (\Exception $e) {
                Log::error('MRP run failed', [
                    'run_id' => $this->currentRun->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'warnings' => $this->warnings,
                ]);

                // Mark as failed with detailed error message
                $errorMessage = $e->getMessage();
                if (!empty($this->warnings)) {
                    $errorMessage .= "\n\nWarnings:\n" . implode("\n", $this->warnings);
                }

                $this->currentRun->markAsFailed($errorMessage);
                
                // Clear caches
                $this->cacheService->clearProgress($this->currentRun->id);
                $this->cacheService->clearPreloadedData($this->currentRun->id);
                
                // Re-throw to trigger transaction rollback
                throw $e;
            } finally {
                // Always release lock
                $this->cacheService->releaseLock($this->companyId, $this->currentRun->id);
            }

            return $this->currentRun->fresh(['recommendations']);
        });

        // If async, dispatch to queue and return immediately
        if ($async) {
            \App\Jobs\ProcessMrpRunJob::dispatch($run->id, $params)
                ->onQueue('mrp'); // Use dedicated queue for MRP

            Log::info('MRP run dispatched to queue', [
                'run_id' => $run->id,
            ]);

            return $run;
        }

        // Otherwise, execute synchronously (existing logic)
        return $run;
    }

    /**
     * Determine if async processing should be used
     */
    protected function shouldUseAsync(array $params): bool
    {
        // Check product count if filters are set
        if (!empty($params['product_filters']['product_ids'])) {
            $productCount = count($params['product_filters']['product_ids']);
            return $productCount >= 1000; // Use queue for 1000+ products
        }

        // Check total active products for company
        $totalProducts = Product::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->count();

        // Use queue for large companies (5000+ products)
        return $totalProducts >= 5000;
    }

    /**
     * Process an existing MRP run (used by queue job)
     */
    public function processExistingRun(MrpRun $run, array $params): void
    {
        $this->companyId = $run->company_id;
        $this->currentRun = $run;
        $this->warnings = [];
        $this->recommendationsGenerated = 0;

        try {
            // Validate critical data before starting
            $this->validateMrpRunData();

            // Acquire distributed lock to prevent concurrent runs
            if (!$this->cacheService->acquireLock($this->companyId, $this->currentRun->id)) {
                throw new \Exception('Another MRP run is already in progress for this company.');
            }

            $this->currentRun->markAsRunning();

            // Execute MRP calculation
            $productsProcessed = $this->executeMrpCalculation();

            // Log warnings if any
            $totalWarnings = $this->getTotalWarningsCount();
            if ($totalWarnings > 0) {
                Log::warning('MRP run completed with warnings', [
                    'run_id' => $this->currentRun->id,
                    'warnings_summary' => $this->warningsSummary,
                    'total_warnings' => $totalWarnings,
                ]);
            }

            $this->currentRun->markAsCompleted(
                $productsProcessed,
                $this->recommendationsGenerated,
                $totalWarnings,
                $this->getWarningsSummary()
            );

            // Clear progress cache
            $this->cacheService->clearProgress($this->currentRun->id);
            $this->cacheService->clearPreloadedData($this->currentRun->id);

            Log::info('MRP run completed', [
                'run_id' => $this->currentRun->id,
                'products_processed' => $productsProcessed,
                'recommendations' => $this->recommendationsGenerated,
                'warnings_count' => $totalWarnings,
            ]);
        } catch (\Exception $e) {
            Log::error('MRP run failed', [
                'run_id' => $this->currentRun->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'warnings' => $this->warnings,
            ]);

            // Mark as failed with detailed error message
            $errorMessage = $e->getMessage();
            if (!empty($this->warnings)) {
                $errorMessage .= "\n\nWarnings:\n" . implode("\n", $this->warnings);
            }

            $this->currentRun->markAsFailed($errorMessage);
            
            // Clear caches
            $this->cacheService->clearProgress($this->currentRun->id);
            $this->cacheService->clearPreloadedData($this->currentRun->id);
            
            throw $e;
        } finally {
            // Always release lock
            $this->cacheService->releaseLock($this->companyId, $this->currentRun->id);
        }
    }

    /**
     * Cancel an MRP run
     */
    public function cancelRun(MrpRun $run): MrpRun
    {
        if (!$run->status->canCancel()) {
            throw new \Exception('This MRP run cannot be cancelled.');
        }

        $run->markAsCancelled();

        return $run->fresh();
    }

    // =========================================
    // MRP Calculation Engine
    // =========================================

    /**
     * Validate critical data before MRP run
     */
    protected function validateMrpRunData(): void
    {
        $errors = [];

        // Validate planning horizon
        if ($this->currentRun->planning_horizon_start >= $this->currentRun->planning_horizon_end) {
            $errors[] = 'Planning horizon start date must be before end date.';
        }

        // Check if there are any active products
        $activeProductCount = Product::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->count();

        if ($activeProductCount === 0) {
            $errors[] = 'No active products found for MRP calculation.';
        }

        // Check if there are any BOMs for manufactured products
        $manufacturedProducts = Product::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->where('make_or_buy', 'make')
            ->count();

        if ($manufacturedProducts > 0) {
            $productsWithoutBom = Product::where('company_id', $this->companyId)
                ->where('is_active', true)
                ->where('make_or_buy', 'make')
                ->whereDoesntHave('boms', function ($q) {
                    $q->where('status', 'active');
                })
                ->count();

            if ($productsWithoutBom > 0) {
                $this->warningsSummary['products_without_bom'] = [
                    'type' => 'Products Without BOM',
                    'count' => $productsWithoutBom,
                    'message' => "{$productsWithoutBom} manufactured product(s) without active BOM found. These will be skipped.",
                ];
            }
        }

        if (!empty($errors)) {
            throw new \Exception('MRP run validation failed: ' . implode(' ', $errors));
        }
    }

    /**
     * Execute the MRP calculation
     * Optimized for large product lists with chunk processing and progress tracking
     * Supports incremental MRP for changed products only
     */
    protected function executeMrpCalculation(): int
    {
        // Step 1: Calculate Low-Level Codes for all products (with cache)
        $this->calculateLowLevelCodes();

        // Step 2: Check if incremental MRP should be used
        $useIncremental = $this->currentRun->net_change 
            && $this->cacheService->shouldUseIncremental($this->companyId);

        if ($useIncremental) {
            return $this->executeIncrementalMrp();
        }

        // Step 3: Full MRP calculation
        return $this->executeFullMrp();
    }

    /**
     * Execute incremental MRP (only changed products)
     */
    protected function executeIncrementalMrp(): int
    {
        $dirtyProductIds = $this->cacheService->getDirtyProducts($this->companyId);
        
        if (empty($dirtyProductIds)) {
            Log::info('Incremental MRP: No dirty products found, skipping', [
                'run_id' => $this->currentRun->id,
            ]);
            return 0;
        }

        Log::info('Incremental MRP: Processing dirty products', [
            'run_id' => $this->currentRun->id,
            'dirty_count' => count($dirtyProductIds),
        ]);

        // Get dirty products and their affected children (lower LLC)
        $dirtyProducts = Product::whereIn('id', $dirtyProductIds)
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->get();

        // Find all products that depend on dirty products (lower LLC)
        // Products that have BOMs containing dirty products as components
        $maxDirtyLLC = $dirtyProducts->max('low_level_code') ?? 0;
        
        $affectedProductIds = Bom::where('company_id', $this->companyId)
            ->where('status', 'active')
            ->whereHas('items', function ($q) use ($dirtyProductIds) {
                $q->whereIn('component_id', $dirtyProductIds);
            })
            ->pluck('product_id')
            ->unique()
            ->toArray();
        
        $affectedProducts = Product::whereIn('id', $affectedProductIds)
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->where('low_level_code', '>', $maxDirtyLLC)
            ->get();

        // Combine dirty and affected products
        $allProductIds = $dirtyProducts->pluck('id')
            ->merge($affectedProducts->pluck('id'))
            ->unique()
            ->toArray();

        $products = Product::whereIn('id', $allProductIds)
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->orderBy('low_level_code', 'asc')
            ->get();

        $totalProducts = $products->count();

        if ($totalProducts === 0) {
            return 0;
        }

        // Pre-load data
        $this->preloadMrpData($products);

        // Process products
        $productsProcessed = $this->processProductsWithMemoryManagement($products, $totalProducts);

        // Clear dirty products after processing
        $this->cacheService->clearDirtyProducts($this->companyId);

        return $productsProcessed;
    }

    /**
     * Execute full MRP calculation
     */
    protected function executeFullMrp(): int
    {
        // Step 1: Get products sorted by Low-Level Code (highest to lowest)
        $products = $this->getProductsToProcess();
        $totalProducts = $products->count();

        if ($totalProducts === 0) {
            return 0;
        }

        // Step 2: Pre-load all data to avoid N+1 queries
        // For very large datasets, use Redis to store pre-loaded data
        $this->preloadMrpData($products);

        // Step 4: Process products (with parallel chunk processing option)
        $productsProcessed = $this->processProductsWithMemoryManagement($products, $totalProducts);

        // Final progress update
        $this->cacheService->updateProgress(
            $this->currentRun->id,
            $productsProcessed,
            $totalProducts,
            null
        );

        return $productsProcessed;
    }

    /**
     * Process products with memory management and optional parallel processing
     */
    protected function processProductsWithMemoryManagement(Collection $products, int $totalProducts): int
    {
        $chunkSize = $this->cacheService->getChunkSize();
        $productsProcessed = 0;
        $dependentDemands = []; // Track dependent demands from parent products

        // Check if parallel processing should be used (for very large runs)
        $useParallel = $totalProducts >= 2000 && config('queue.default') !== 'sync';

        if ($useParallel) {
            // Process chunks in parallel using queue jobs
            return $this->processProductsInParallel($products, $chunkSize, $totalProducts);
        }

        // Sequential processing with memory management
        $productChunks = $products->chunk($chunkSize);

        foreach ($productChunks as $chunk) {
            foreach ($chunk as $product) {
                $this->processProduct($product, $dependentDemands);
                $productsProcessed++;

                // Update progress every 10 products
                if ($productsProcessed % 10 === 0) {
                    $this->cacheService->updateProgress(
                        $this->currentRun->id,
                        $productsProcessed,
                        $totalProducts,
                        $product->sku
                    );
                }
            }

            // Aggressive memory cleanup after each chunk
            unset($chunk);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Force garbage collection
            if (function_exists('gc_mem_caches')) {
                gc_mem_caches();
            }
        }

        return $productsProcessed;
    }

    /**
     * Process products in parallel using queue jobs
     */
    protected function processProductsInParallel(Collection $products, int $chunkSize, int $totalProducts): int
    {
        $chunks = $products->chunk($chunkSize);
        $chunkCount = $chunks->count();
        $productIds = $products->pluck('id')->toArray();

        Log::info('Processing MRP in parallel mode', [
            'run_id' => $this->currentRun->id,
            'total_products' => $totalProducts,
            'chunk_count' => $chunkCount,
            'chunk_size' => $chunkSize,
        ]);

        // Dispatch chunks to queue
        $chunkIndex = 0;
        foreach ($chunks as $chunk) {
            $chunkProductIds = $chunk->pluck('id')->toArray();
            
            \App\Jobs\ProcessMrpChunkJob::dispatch(
                $this->currentRun->id,
                $chunkProductIds,
                $this->getMrpParams()
            )->onQueue('mrp-chunks');

            $chunkIndex++;
            
            // Update progress
            $this->cacheService->updateProgress(
                $this->currentRun->id,
                0,
                $totalProducts,
                "Dispatching chunk {$chunkIndex}/{$chunkCount}"
            );
        }

        // For parallel processing, return estimated count
        // Actual processing happens in background
        return $totalProducts;
    }

    /**
     * Process a chunk of products (used by parallel jobs)
     */
    public function processProductChunk(MrpRun $run, Collection $products, array $params): int
    {
        $this->companyId = $run->company_id;
        $this->currentRun = $run;
        $this->warnings = [];
        $this->recommendationsGenerated = 0;

        // Pre-load data for this chunk
        $this->preloadMrpData($products);

        $productsProcessed = 0;
        $dependentDemands = [];

        foreach ($products as $product) {
            $this->processProduct($product, $dependentDemands);
            $productsProcessed++;
        }

        // Memory cleanup
        unset($products, $dependentDemands);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return $productsProcessed;
    }

    /**
     * Get MRP parameters for job serialization
     */
    protected function getMrpParams(): array
    {
        return [
            'planning_horizon_start' => $this->currentRun->planning_horizon_start,
            'planning_horizon_end' => $this->currentRun->planning_horizon_end,
            'include_safety_stock' => $this->currentRun->include_safety_stock,
            'respect_lead_times' => $this->currentRun->respect_lead_times,
            'consider_wip' => $this->currentRun->consider_wip,
            'warehouse_filters' => $this->currentRun->warehouse_filters,
        ];
    }

    /**
     * Calculate Low-Level Codes for all products
     * Low-Level Code determines the processing order in MRP:
     * - Level 0: Finished goods (not used as components)
     * - Level 1+: Components used in higher-level products
     * 
     * Uses Redis cache for performance optimization
     */
    protected function calculateLowLevelCodes(): void
    {
        // Check cache first (if BOMs haven't changed)
        $cachedCodes = $this->cacheService->getCachedLowLevelCodes($this->companyId);
        if ($cachedCodes !== null) {
            // Apply cached codes
            foreach ($cachedCodes as $productId => $level) {
                Product::where('id', $productId)
                    ->where('company_id', $this->companyId)
                    ->update(['low_level_code' => $level]);
            }
            Log::info('Low-Level Codes loaded from cache', ['company_id' => $this->companyId]);
            return;
        }

        // Reset all low-level codes to 0
        Product::where('company_id', $this->companyId)
            ->update(['low_level_code' => 0]);

        $changed = true;
        $maxIterations = 100; // Prevent infinite loops
        $iteration = 0;
        $codesToUpdate = []; // Batch updates instead of individual saves

        while ($changed && $iteration < $maxIterations) {
            $changed = false;
            $iteration++;

            // Get all active BOMs (cache BOM structure if possible)
            $boms = Bom::where('company_id', $this->companyId)
                ->where('status', 'active')
                ->with('items.component')
                ->get();

            foreach ($boms as $bom) {
                $parentProduct = $bom->product;
                if (!$parentProduct || !$parentProduct->is_active) {
                    continue;
                }

                $parentLevel = $parentProduct->low_level_code ?? 0;

                // Check all components in this BOM
                foreach ($bom->items as $item) {
                    $component = $item->component;
                    if (!$component || !$component->is_active) {
                        continue;
                    }

                    // Component's level should be at least parent's level + 1
                    $requiredLevel = $parentLevel + 1;
                    $currentLevel = $component->low_level_code ?? 0;

                    if ($requiredLevel > $currentLevel) {
                        $codesToUpdate[$component->id] = $requiredLevel;
                        $component->low_level_code = $requiredLevel;
                        $changed = true;
                    }
                }
            }

            // Batch update products
            if (!empty($codesToUpdate)) {
                foreach ($codesToUpdate as $productId => $level) {
                    Product::where('id', $productId)
                        ->where('company_id', $this->companyId)
                        ->update(['low_level_code' => $level]);
                }
                $codesToUpdate = [];
            }
        }

        if ($iteration >= $maxIterations) {
            $this->warningsSummary['llc_calculation_warning'] = [
                'type' => 'Low-Level Code Calculation Warning',
                'count' => 1,
                'message' => 'Low-Level Code calculation reached maximum iterations. Possible circular BOM reference.',
            ];
            Log::warning('Low-Level Code calculation reached max iterations', [
                'company_id' => $this->companyId,
            ]);
        }

        // Cache the results
        $finalCodes = Product::where('company_id', $this->companyId)
            ->pluck('low_level_code', 'id')
            ->toArray();
        $this->cacheService->cacheLowLevelCodes($this->companyId, $finalCodes);
    }

    /**
     * Get products to process based on filters, sorted by Low-Level Code
     */
    protected function getProductsToProcess(): Collection
    {
        $query = Product::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->orderBy('low_level_code', 'asc'); // Process from highest level (0) to lowest

        // Apply product filters if set
        if (!empty($this->currentRun->product_filters)) {
            $filters = $this->currentRun->product_filters;

            if (!empty($filters['product_ids'])) {
                $query->whereIn('id', $filters['product_ids']);
            }

            if (!empty($filters['category_ids'])) {
                $query->whereHas('categories', function ($q) use ($filters) {
                    $q->whereIn('categories.id', $filters['category_ids']);
                });
            }

            if (!empty($filters['make_or_buy'])) {
                $query->where('make_or_buy', $filters['make_or_buy']);
            }
        }

        return $query->get();
    }

    /**
     * Pre-load MRP data to avoid N+1 queries
     */
    protected function preloadMrpData(Collection $products): void
    {
        $productIds = $products->pluck('id')->toArray();
        $startDate = $this->currentRun->planning_horizon_start;
        $endDate = $this->currentRun->planning_horizon_end;

        // Initialize collections
        $this->preloadedStocks = collect();
        $this->preloadedSalesDemands = collect();
        $this->preloadedPoReceipts = collect();
        $this->preloadedWoDemands = collect();
        $this->preloadedWoReceipts = collect();

        if (empty($productIds)) {
            return;
        }

        // Pre-load stocks
        $stockQuery = Stock::whereIn('product_id', $productIds)
            ->qualityAvailable();

        if (!empty($this->currentRun->warehouse_filters)) {
            $filters = $this->currentRun->warehouse_filters;
            
            // Include specific warehouses
            if (!empty($filters['include'])) {
                $stockQuery->whereIn('warehouse_id', $filters['include']);
            }
            
            // Exclude specific warehouses
            if (!empty($filters['exclude'])) {
                $stockQuery->whereNotIn('warehouse_id', $filters['exclude']);
            }
        }

        $this->preloadedStocks = $stockQuery->get()->groupBy('product_id');

        // Pre-load sales order demands
        // Include: approved, pending_approval, confirmed, processing, partially_shipped
        // (approved and pending_approval are included because they represent committed demand)
        $this->preloadedSalesDemands = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->where('sales_orders.company_id', $this->companyId)
            ->whereIn('sales_order_items.product_id', $productIds)
            ->whereIn('sales_orders.status', ['approved', 'pending_approval', 'confirmed', 'processing', 'partially_shipped'])
            ->where(function ($query) use ($startDate, $endDate) {
                // Use requested_delivery_date if available, otherwise fall back to order_date
                $query->whereBetween(
                    DB::raw('COALESCE(sales_orders.requested_delivery_date, sales_orders.order_date)'),
                    [$startDate, $endDate]
                );
            })
            ->select([
                'sales_order_items.product_id',
                'sales_orders.id as source_id',
                DB::raw('COALESCE(sales_orders.requested_delivery_date, sales_orders.order_date) as required_date'),
                DB::raw('(sales_order_items.quantity_ordered - COALESCE(sales_order_items.quantity_shipped, 0)) as quantity'),
            ])
            ->get()
            ->groupBy('product_id');

        // Debug: Log how many sales order demands were found
        $totalSalesDemands = $this->preloadedSalesDemands->sum(function ($group) {
            return $group->count();
        });
        Log::info('MRP: Pre-loaded sales order demands', [
            'run_id' => $this->currentRun->id,
            'total_demands' => $totalSalesDemands,
            'planning_horizon' => [$startDate, $endDate],
            'products_with_demands' => $this->preloadedSalesDemands->count(),
        ]);

        // Pre-load purchase order receipts
        $this->preloadedPoReceipts = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.company_id', $this->companyId)
            ->whereIn('purchase_order_items.product_id', $productIds)
            ->whereIn('purchase_orders.status', ['approved', 'sent', 'partially_received'])
            ->whereBetween('purchase_orders.expected_delivery_date', [$startDate, $endDate])
            ->select([
                'purchase_order_items.product_id',
                'purchase_orders.id as source_id',
                'purchase_orders.expected_delivery_date as receipt_date',
                DB::raw('(purchase_order_items.quantity_ordered - COALESCE(purchase_order_items.quantity_received, 0)) as quantity'),
            ])
            ->get()
            ->groupBy('product_id');

        // Pre-load work order demands (if considering WIP)
        if ($this->currentRun->consider_wip) {
            $this->preloadedWoDemands = DB::table('work_order_materials')
                ->join('work_orders', 'work_orders.id', '=', 'work_order_materials.work_order_id')
                ->where('work_orders.company_id', $this->companyId)
                ->whereIn('work_order_materials.product_id', $productIds)
                ->whereIn('work_orders.status', ['released', 'in_progress'])
                ->whereBetween('work_orders.planned_start_date', [$startDate, $endDate])
                ->select([
                    'work_order_materials.product_id',
                    'work_orders.id as source_id',
                    'work_orders.planned_start_date as required_date',
                    DB::raw('(work_order_materials.quantity_required - COALESCE(work_order_materials.quantity_issued, 0)) as quantity'),
                ])
                ->get()
                ->groupBy('product_id');

            $this->preloadedWoReceipts = DB::table('work_orders')
                ->where('company_id', $this->companyId)
                ->whereIn('product_id', $productIds)
                ->whereIn('status', ['released', 'in_progress'])
                ->whereBetween('planned_end_date', [$startDate, $endDate])
                ->select([
                    'product_id',
                    'id as source_id',
                    'planned_end_date as receipt_date',
                    DB::raw('(quantity_ordered - quantity_completed - quantity_scrapped) as quantity'),
                ])
                ->get()
                ->groupBy('product_id');
        }
    }

    /**
     * Process a single product for MRP
     * 
     * @param Product $product The product to process
     * @param array $dependentDemands Reference to dependent demands array (passed by reference)
     */
    protected function processProduct(Product $product, array &$dependentDemands): void
    {
        try {
            // Get current stock
            $currentStock = $this->getCurrentStock($product);

            // Get independent demand (sales orders, manual work orders)
            $independentDemands = $this->getIndependentDemands($product);

            // Get dependent demand (from parent products that need this as component)
            $dependentDemandForProduct = $dependentDemands[$product->id] ?? collect();

            // Merge all demands
            $allDemands = $independentDemands->merge($dependentDemandForProduct)->sortBy('required_date');

            // Get scheduled receipts (open POs, WOs producing this product)
            $scheduledReceipts = $this->getScheduledReceipts($product);

            // Debug logging for products with demands but no recommendations
            if ($allDemands->isNotEmpty() && $currentStock > 0) {
                Log::debug('MRP: Processing product with demands', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'current_stock' => $currentStock,
                    'independent_demands_count' => $independentDemands->count(),
                    'dependent_demands_count' => $dependentDemandForProduct->count(),
                    'total_demands_count' => $allDemands->count(),
                    'scheduled_receipts_count' => $scheduledReceipts->count(),
                    'safety_stock' => $product->safety_stock,
                ]);
            }

            // Calculate net requirements
            $netRequirements = $this->calculateNetRequirements(
                $product,
                $currentStock,
                $allDemands,
                $scheduledReceipts
            );

            // Debug logging if no net requirements despite having demands
            if ($allDemands->isNotEmpty() && empty($netRequirements)) {
                Log::debug('MRP: No net requirements despite having demands', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'current_stock' => $currentStock,
                    'safety_stock' => $product->safety_stock,
                    'total_demands' => $allDemands->sum('quantity'),
                    'include_safety_stock' => $this->currentRun->include_safety_stock,
                ]);
            }

            // Generate recommendations
            $this->generateRecommendations($product, $netRequirements);

            // If this product has a BOM and we're generating work orders, explode BOM to create dependent demands
            if ($product->shouldManufacture() && !empty($netRequirements)) {
                $this->explodeBomForDependentDemands($product, $netRequirements, $dependentDemands);
            }
        } catch (\Exception $e) {
            // Group warnings by error type instead of storing individual messages
            if (!isset($this->warningsSummary['product_processing_errors'])) {
                $this->warningsSummary['product_processing_errors'] = [
                    'type' => 'Product Processing Errors',
                    'count' => 0,
                    'examples' => [],
                ];
            }
            $this->warningsSummary['product_processing_errors']['count']++;
            if (count($this->warningsSummary['product_processing_errors']['examples']) < 3) {
                $this->warningsSummary['product_processing_errors']['examples'][] = [
                    'product_sku' => $product->sku,
                    'error' => $e->getMessage(),
                ];
            }
            
            Log::error('Error processing product in MRP', [
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Explode BOM to create dependent demands for components
     */
    protected function explodeBomForDependentDemands(Product $product, array $netRequirements, array &$dependentDemands): void
    {
        $defaultBom = $product->defaultBom;
        if (!$defaultBom) {
            return;
        }

        try {
            // Group net requirements by date
            $requirementsByDate = collect($netRequirements)->groupBy(function ($req) {
                return $req['date']->toDateString();
            });

            foreach ($requirementsByDate as $dateString => $requirements) {
                $totalQtyNeeded = $requirements->sum('net_requirement');

                if ($totalQtyNeeded <= 0) {
                    continue;
                }

                // Check cache first for BOM explosion
                $explodedMaterials = $this->cacheService->getCachedBomExplosion($product->id, $totalQtyNeeded);
                
                if ($explodedMaterials === null) {
                    // Explode BOM for this quantity
                    $explodedMaterials = $this->bomService->explodeBom($defaultBom, $totalQtyNeeded, 10, false);
                    
                    // Cache the result
                    $this->cacheService->cacheBomExplosion($product->id, $totalQtyNeeded, $explodedMaterials);
                }

                // Create dependent demands for each component
                foreach ($explodedMaterials as $material) {
                    $componentId = $material['product_id'];
                    $componentQty = $material['quantity'];
                    $requiredDate = Carbon::parse($dateString);

                    // Adjust required date based on product's lead time (backward scheduling)
                    // Component is needed before parent product can be completed
                    $componentRequiredDate = $this->calculateWorkingDate(
                        $requiredDate->copy()->subDays($product->lead_time_days ?? 0)
                    );

                    if (!isset($dependentDemands[$componentId])) {
                        $dependentDemands[$componentId] = collect();
                    }

                    // Check if demand already exists for this date
                    $existingDemand = $dependentDemands[$componentId]->first(function ($demand) use ($componentRequiredDate) {
                        return $demand['required_date']->toDateString() === $componentRequiredDate->toDateString();
                    });

                    if ($existingDemand) {
                        // Add to existing demand
                        $existingDemand['quantity'] += $componentQty;
                    } else {
                        // Create new dependent demand
                        $dependentDemands[$componentId]->push([
                            'source_type' => 'dependent_demand',
                            'source_id' => $product->id,
                            'source_sku' => $product->sku,
                            'required_date' => $componentRequiredDate,
                            'quantity' => $componentQty,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Group warnings by error type
            if (!isset($this->warningsSummary['bom_explosion_errors'])) {
                $this->warningsSummary['bom_explosion_errors'] = [
                    'type' => 'BOM Explosion Errors',
                    'count' => 0,
                    'examples' => [],
                ];
            }
            $this->warningsSummary['bom_explosion_errors']['count']++;
            if (count($this->warningsSummary['bom_explosion_errors']['examples']) < 3) {
                $this->warningsSummary['bom_explosion_errors']['examples'][] = [
                    'product_sku' => $product->sku,
                    'error' => $e->getMessage(),
                ];
            }
            
            Log::error('Error exploding BOM in MRP', [
                'product_id' => $product->id,
                'bom_id' => $defaultBom->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current stock for a product (using pre-loaded data)
     */
    protected function getCurrentStock(Product $product): float
    {
        if (isset($this->preloadedStocks[$product->id])) {
            return $this->preloadedStocks[$product->id]->sum('quantity_available');
        }

        // Fallback if not pre-loaded
        $query = Stock::where('product_id', $product->id)
            ->qualityAvailable();

        if (!empty($this->currentRun->warehouse_filters)) {
            $filters = $this->currentRun->warehouse_filters;
            
            // Include specific warehouses
            if (!empty($filters['include'])) {
                $query->whereIn('warehouse_id', $filters['include']);
            }
            
            // Exclude specific warehouses
            if (!empty($filters['exclude'])) {
                $query->whereNotIn('warehouse_id', $filters['exclude']);
            }
        }

        return $query->sum('quantity_available');
    }

    /**
     * Get independent demands for a product (sales orders, manual work orders)
     * Uses pre-loaded data for performance
     */
    protected function getIndependentDemands(Product $product): Collection
    {
        $demands = collect();

        // Sales Order demands (pre-loaded)
        if (isset($this->preloadedSalesDemands[$product->id])) {
            $salesDemands = $this->preloadedSalesDemands[$product->id]->map(function ($item) {
                return [
                    'source_type' => 'sales_order',
                    'source_id' => $item->source_id,
                    'required_date' => Carbon::parse($item->required_date),
                    'quantity' => (float) $item->quantity,
                ];
            });
            $demands = $demands->merge($salesDemands);
        }

        // Work Order component demands (pre-loaded, if considering WIP)
        if ($this->currentRun->consider_wip && isset($this->preloadedWoDemands[$product->id])) {
            $woDemands = $this->preloadedWoDemands[$product->id]->map(function ($item) {
                return [
                    'source_type' => 'work_order',
                    'source_id' => $item->source_id,
                    'required_date' => Carbon::parse($item->required_date),
                    'quantity' => (float) $item->quantity,
                ];
            });
            $demands = $demands->merge($woDemands);
        }

        // Sort by required date
        return $demands->sortBy('required_date');
    }

    /**
     * Get scheduled receipts for a product (using pre-loaded data)
     */
    protected function getScheduledReceipts(Product $product): Collection
    {
        $receipts = collect();

        // Purchase Order receipts (pre-loaded)
        if (isset($this->preloadedPoReceipts[$product->id])) {
            $poReceipts = $this->preloadedPoReceipts[$product->id]->map(function ($item) {
                return [
                    'source_type' => 'purchase_order',
                    'source_id' => $item->source_id,
                    'receipt_date' => Carbon::parse($item->receipt_date),
                    'quantity' => (float) $item->quantity,
                ];
            });
            $receipts = $receipts->merge($poReceipts);
        }

        // Work Order receipts (pre-loaded, if considering WIP)
        if ($this->currentRun->consider_wip && isset($this->preloadedWoReceipts[$product->id])) {
            $woReceipts = $this->preloadedWoReceipts[$product->id]->map(function ($item) {
                return [
                    'source_type' => 'work_order_output',
                    'source_id' => $item->source_id,
                    'receipt_date' => Carbon::parse($item->receipt_date),
                    'quantity' => (float) $item->quantity,
                ];
            });
            $receipts = $receipts->merge($woReceipts);
        }

        return $receipts->sortBy('receipt_date');
    }

    /**
     * Calculate net requirements using time-phased calculation
     */
    protected function calculateNetRequirements(
        Product $product,
        float $currentStock,
        Collection $demands,
        Collection $scheduledReceipts
    ): array {
        $requirements = [];
        $projectedStock = $currentStock;
        $safetyStock = $this->currentRun->include_safety_stock ? ($product->safety_stock ?? 0) : 0;

        // If current stock is negative, this is a priority requirement
        $negativeStock = min(0, $currentStock);
        $hasNegativeStock = $negativeStock < 0;
        $negativeStockImpact = $hasNegativeStock ? abs($negativeStock) : 0;

        // Group demands by date
        $demandsByDate = $demands->groupBy(function ($demand) {
            return $demand['required_date']->toDateString();
        });

        // Group receipts by date
        $receiptsByDate = $scheduledReceipts->groupBy(function ($receipt) {
            return $receipt['receipt_date']->toDateString();
        });

        // Get all dates in planning horizon
        $startDate = $this->currentRun->planning_horizon_start;
        $endDate = $this->currentRun->planning_horizon_end;
        $currentDate = Carbon::parse($startDate);

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();

            // Add scheduled receipts for this date
            if (isset($receiptsByDate[$dateString])) {
                foreach ($receiptsByDate[$dateString] as $receipt) {
                    $projectedStock += $receipt['quantity'];
                }
            }

            // Subtract demands for this date
            $dayDemands = $demandsByDate[$dateString] ?? collect();
            $totalDemand = $dayDemands->sum('quantity');

            if ($totalDemand > 0) {
                // If negative stock exists and this is the first demand, add negative stock impact
                $adjustedDemand = $totalDemand;
                if ($hasNegativeStock && $projectedStock < 0) {
                    $adjustedDemand += $negativeStockImpact;
                    $hasNegativeStock = false; // Only add to first demand
                }

                $projectedStock -= $totalDemand;

                // Check if we fall below safety stock
                if ($projectedStock < $safetyStock) {
                    $shortage = $safetyStock - $projectedStock;

                    $requirements[] = [
                        'date' => $currentDate->copy(),
                        'gross_requirement' => $totalDemand,
                        'net_requirement' => $shortage,
                        'projected_stock' => $projectedStock,
                        'negative_stock_impact' => $hasNegativeStock ? $negativeStockImpact : 0,
                        'priority' => ($hasNegativeStock || $projectedStock < 0) ? 'high' : 'normal',
                        'demands' => $dayDemands->toArray(),
                    ];
                }
            }

            $currentDate->addDay();
        }

        return $requirements;
    }

    /**
     * Generate recommendations for net requirements
     */
    protected function generateRecommendations(Product $product, array $netRequirements): void
    {
        $currentStock = $this->getCurrentStock($product);
        $hasNegativeStock = $currentStock < 0;
        foreach ($netRequirements as $requirement) {
            $suggestedQty = $product->calculateOrderQuantity($requirement['net_requirement']);

            if ($suggestedQty <= 0) {
                Log::debug('MRP: Skipping recommendation - suggested quantity <= 0', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'net_requirement' => $requirement['net_requirement'],
                    'suggested_qty' => $suggestedQty,
                    'minimum_order_qty' => $product->minimum_order_qty,
                    'order_multiple' => $product->order_multiple,
                ]);
                continue;
            }

            // Determine recommendation type based on make_or_buy
            $type = $product->shouldManufacture()
                ? MrpRecommendationType::WORK_ORDER
                : MrpRecommendationType::PURCHASE_ORDER;

            // Calculate suggested order date (considering lead time and working days)
            $requiredDate = $requirement['date'];
            $suggestedDate = $this->currentRun->respect_lead_times
                ? $this->calculateOrderDate($product, $requiredDate)
                : $requiredDate;

            // Determine priority based on urgency and negative stock
            $priority = $this->determinePriority($suggestedDate);
            $isUrgent = $suggestedDate <= today();
            $urgencyReason = null;

            // If negative stock exists, mark as high priority
            if ($hasNegativeStock || ($requirement['negative_stock_impact'] ?? 0) > 0) {
                $priority = MrpPriority::HIGH;
                $urgencyReason = 'Negative stock status: ' . abs($currentStock) . ' units. Priority requirement.';
            } elseif ($isUrgent) {
                $urgencyReason = 'Order date is today or in the past - immediate action required';
            } elseif ($suggestedDate <= today()->addDays(3)) {
                $urgencyReason = 'Order date is within 3 days';
            }

            // Get demand source info
            $demandSource = $requirement['demands'][0] ?? null;

            MrpRecommendation::create([
                'company_id' => $this->companyId,
                'mrp_run_id' => $this->currentRun->id,
                'product_id' => $product->id,
                'warehouse_id' => null, // Could be enhanced to specify warehouse
                'recommendation_type' => $type,
                'required_date' => $requiredDate,
                'suggested_date' => $suggestedDate,
                'due_date' => $requiredDate,
                'gross_requirement' => $requirement['gross_requirement'],
                'net_requirement' => $requirement['net_requirement'],
                'suggested_quantity' => $suggestedQty,
                'current_stock' => $requirement['projected_stock'] + $requirement['net_requirement'],
                'projected_stock' => $requirement['projected_stock'] + $suggestedQty,
                'demand_source_type' => $demandSource['source_type'] ?? null,
                'demand_source_id' => $demandSource['source_id'] ?? null,
                'priority' => $priority,
                'is_urgent' => $isUrgent,
                'urgency_reason' => $urgencyReason,
                'status' => MrpRecommendationStatus::PENDING,
                'calculation_details' => [
                    'safety_stock' => $product->safety_stock,
                    'lead_time_days' => $product->lead_time_days,
                    'minimum_order_qty' => $product->minimum_order_qty,
                    'order_multiple' => $product->order_multiple,
                    'negative_stock_impact' => $requirement['negative_stock_impact'] ?? 0,
                    'demands' => $requirement['demands'],
                ],
            ]);

            $this->recommendationsGenerated++;
        }
    }

    /**
     * Calculate order date considering lead time and working days
     * Uses working hours to calculate more accurately
     */
    protected function calculateOrderDate(Product $product, Carbon $requiredDate): Carbon
    {
        $leadTimeDays = $product->lead_time_days ?? 0;
        
        if ($leadTimeDays <= 0) {
            return $requiredDate->copy();
        }

        // Calculate working days backward from required date
        // Note: This uses calendar days, but only counts working days
        // For more precise calculation with hours, we could enhance this later
        $orderDate = $requiredDate->copy();
        $workingDaysToSubtract = $leadTimeDays;

        while ($workingDaysToSubtract > 0) {
            $orderDate->subDay();
            
            // Check if this is a working day (considers company calendar overrides)
            if ($this->isWorkingDay($orderDate)) {
                $workingDaysToSubtract--;
            }
        }

        return $orderDate;
    }

    /**
     * Calculate next working date from a given date
     * Skips weekends and holidays
     */
    protected function calculateWorkingDate(Carbon $date): Carbon
    {
        $workingDate = $date->copy();
        
        // Move forward until we find a working day
        while (!$this->isWorkingDay($workingDate)) {
            $workingDate->addDay();
        }

        return $workingDate;
    }

    /**
     * Check if a date is a working day
     * Priority:
     * 1. Company calendar override (specific date)
     * 2. Standard working days from settings
     */
    protected function isWorkingDay(Carbon $date): bool
    {
        // First, check company calendar for specific date override
        $calendarEntry = CompanyCalendar::where('company_id', $this->companyId)
            ->forDate($date->toDateString())
            ->first();

        if ($calendarEntry) {
            // Company calendar entry overrides standard rules
            return $calendarEntry->isWorkingDay();
        }

        // No calendar override, check standard working days from settings
        // Format: [1,2,3,4,5] where 0=Sunday, 1=Monday, ..., 6=Saturday
        $workingDays = Setting::get('mrp.working_days', [1, 2, 3, 4, 5]); // Default: Mon-Fri
        
        // Ensure it's an array
        if (!is_array($workingDays)) {
            $workingDays = [1, 2, 3, 4, 5]; // Fallback to Mon-Fri
        }

        // Get day of week (0=Sunday, 1=Monday, ..., 6=Saturday)
        $dayOfWeek = (int) $date->dayOfWeek;

        // Check if this day is in the working days array
        return in_array($dayOfWeek, $workingDays, true);
    }

    /**
     * Get working hours for a specific date
     * Returns hours available for work on this date
     */
    protected function getWorkingHours(Carbon $date): float
    {
        // First, check company calendar for specific date override
        $calendarEntry = CompanyCalendar::where('company_id', $this->companyId)
            ->forDate($date->toDateString())
            ->first();

        if ($calendarEntry) {
            $hours = $calendarEntry->getEffectiveWorkingHours();
            if ($hours !== null) {
                return $hours;
            }
        }

        // No calendar override, use default shift from settings
        $defaultShift = Setting::get('mrp.default_shift', [
            'working_hours' => 8.0,
        ]);

        return (float) ($defaultShift['working_hours'] ?? 8.0);
    }

    /**
     * Determine priority based on suggested date
     */
    protected function determinePriority(Carbon $suggestedDate): MrpPriority
    {
        $daysUntil = today()->diffInDays($suggestedDate, false);

        if ($daysUntil < 0) {
            return MrpPriority::CRITICAL; // Past due
        } elseif ($daysUntil <= 3) {
            return MrpPriority::HIGH;
        } elseif ($daysUntil <= 7) {
            return MrpPriority::MEDIUM;
        }

        return MrpPriority::LOW;
    }

    // =========================================
    // Recommendation Management
    // =========================================

    /**
     * Get recommendations for an MRP run
     */
    public function getRecommendations(MrpRun $run, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $run->recommendations()
            ->with(['product:id,name,sku', 'warehouse:id,name,code']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('recommendation_type', $filters['type']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['urgent_only'])) {
            $query->urgent();
        }

        return $query->byPriority()
            ->orderBy('required_date')
            ->paginate($perPage);
    }

    /**
     * Approve a recommendation
     */
    public function approveRecommendation(MrpRecommendation $recommendation): MrpRecommendation
    {
        if (!$recommendation->status->canApprove()) {
            throw new \Exception('Recommendation cannot be approved.');
        }

        DB::beginTransaction();

        try {
            // Update status to APPROVED first
            $recommendation->update([
                'status' => MrpRecommendationStatus::APPROVED,
            ]);

            // Automatically create Purchase Order or Work Order based on recommendation type
            if ($recommendation->recommendation_type === MrpRecommendationType::PURCHASE_ORDER) {
                $this->createPurchaseOrderFromRecommendation($recommendation);
            } elseif ($recommendation->recommendation_type === MrpRecommendationType::WORK_ORDER) {
                $this->createWorkOrderFromRecommendation($recommendation);
            }

            DB::commit();

            return $recommendation->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve MRP recommendation and create document', [
                'recommendation_id' => $recommendation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create Purchase Order from MRP recommendation
     */
    protected function createPurchaseOrderFromRecommendation(MrpRecommendation $recommendation): void
    {
        $product = $recommendation->product;
        $companyId = $recommendation->company_id;

        // Try to get supplier for the product (optional - can be assigned later)
        $supplierService = app(\App\Services\SupplierService::class);
        $supplier = $supplierService->getPreferredSupplier($product->id);

        if (!$supplier) {
            // Try to get any active supplier for this product
            $suppliers = $supplierService->getSuppliersForProduct($product->id);
            $supplier = $suppliers->first();
        }

        // Supplier is optional at creation - will be required during approval
        // If no supplier found, PO will be created without supplier and user can assign it later

        // Get warehouse (from recommendation or use default)
        $warehouseId = $recommendation->warehouse_id;
        if (!$warehouseId) {
            $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (!$warehouse) {
                $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$warehouse) {
                throw new \Exception('No warehouse found. Please create a warehouse first.');
            }

            $warehouseId = $warehouse->id;
        }

        // Get supplier product info for pricing (if supplier exists)
        $unitPrice = $product->cost_price ?? $product->price ?? 0;
        if ($supplier) {
            $supplierProduct = $supplier->products()
                ->where('product_id', $product->id)
                ->where('supplier_products.is_active', true)
                ->first();
            
            $unitPrice = $supplierProduct?->pivot?->unit_price ?? $unitPrice;
        }
        $uomId = $product->uom_id ?? 1;

        // Create Purchase Order
        $purchaseOrderService = app(\App\Services\PurchaseOrderService::class);
        $purchaseOrder = $purchaseOrderService->create([
            'supplier_id' => $supplier?->id, // Optional - can be null
            'warehouse_id' => $warehouseId,
            'order_date' => $recommendation->suggested_date,
            'expected_delivery_date' => $recommendation->required_date,
            'status' => PoStatus::DRAFT->value,
            'notes' => "Auto-generated from MRP Recommendation #{$recommendation->id}",
            'internal_notes' => "MRP Run: {$recommendation->mrpRun->run_number}",
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_ordered' => $recommendation->suggested_quantity,
                    'uom_id' => $uomId,
                    'unit_price' => $unitPrice,
                    'expected_delivery_date' => $recommendation->required_date,
                ],
            ],
        ]);

        // Link recommendation to purchase order
        $purchaseOrder->update([
            'mrp_recommendation_id' => $recommendation->id,
        ]);

        // Mark recommendation as actioned
        $recommendation->markAsActioned(
            \App\Models\PurchaseOrder::class,
            $purchaseOrder->id,
            "Purchase Order {$purchaseOrder->order_number} created automatically",
            Auth::id()
        );

        Log::info('Purchase Order created from MRP recommendation', [
            'recommendation_id' => $recommendation->id,
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
        ]);
    }

    /**
     * Create Work Order from MRP recommendation
     */
    protected function createWorkOrderFromRecommendation(MrpRecommendation $recommendation): void
    {
        $product = $recommendation->product;
        $companyId = $recommendation->company_id;

        // Get default BOM for the product
        $bomService = app(\App\Services\BomService::class);
        $bom = $bomService->getDefaultBomForProduct($product->id);

        if (!$bom) {
            throw new \Exception("No active BOM found for product: {$product->sku}. Please create a BOM for this product.");
        }

        // Get default routing for the product
        $routingService = app(\App\Services\RoutingService::class);
        $routing = $routingService->getDefaultRoutingForProduct($product->id);

        // Get warehouse (from recommendation or use default)
        $warehouseId = $recommendation->warehouse_id;
        if (!$warehouseId) {
            $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (!$warehouse) {
                $warehouse = \App\Models\Warehouse::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$warehouse) {
                throw new \Exception('No warehouse found. Please create a warehouse first.');
            }

            $warehouseId = $warehouse->id;
        }

        // Create Work Order
        $workOrderService = app(\App\Services\WorkOrderService::class);
        $workOrder = $workOrderService->create([
            'product_id' => $product->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing?->id,
            'warehouse_id' => $warehouseId,
            'quantity_ordered' => $recommendation->suggested_quantity,
            'priority' => $recommendation->priority->value,
            'planned_start_date' => $recommendation->suggested_date,
            'planned_end_date' => $recommendation->required_date,
            'status' => WorkOrderStatus::DRAFT->value,
            'notes' => "Auto-generated from MRP Recommendation #{$recommendation->id}",
        ]);

        // Link recommendation to work order
        $workOrder->update([
            'mrp_recommendation_id' => $recommendation->id,
        ]);

        // Mark recommendation as actioned
        $recommendation->markAsActioned(
            \App\Models\WorkOrder::class,
            $workOrder->id,
            "Work Order {$workOrder->work_order_number} created automatically",
            Auth::id()
        );

        Log::info('Work Order created from MRP recommendation', [
            'recommendation_id' => $recommendation->id,
            'work_order_id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
        ]);
    }

    /**
     * Reject a recommendation
     */
    public function rejectRecommendation(MrpRecommendation $recommendation, ?string $notes = null): MrpRecommendation
    {
        if (!$recommendation->reject($notes, Auth::id())) {
            throw new \Exception('Recommendation cannot be rejected.');
        }

        return $recommendation->fresh();
    }

    /**
     * Bulk approve recommendations
     */
    public function bulkApprove(array $ids): int
    {
        $count = 0;

        $recommendations = MrpRecommendation::whereIn('id', $ids)
            ->where('status', MrpRecommendationStatus::PENDING)
            ->get();

        foreach ($recommendations as $recommendation) {
            if ($recommendation->approve()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk reject recommendations
     */
    public function bulkReject(array $ids, ?string $notes = null): int
    {
        $count = 0;

        $recommendations = MrpRecommendation::whereIn('id', $ids)
            ->where('status', MrpRecommendationStatus::PENDING)
            ->get();

        foreach ($recommendations as $recommendation) {
            if ($recommendation->reject($notes, Auth::id())) {
                $count++;
            }
        }

        return $count;
    }

    // =========================================
    // Statistics and Reports
    // =========================================

    /**
     * Get MRP statistics
     */
    public function getStatistics(): array
    {
        $companyId = Auth::user()->company_id;

        // Get latest run
        $latestRun = MrpRun::where('company_id', $companyId)
            ->completed()
            ->latest()
            ->first();

        $pendingRecommendations = MrpRecommendation::where('company_id', $companyId)
            ->pending()
            ->count();

        $urgentRecommendations = MrpRecommendation::where('company_id', $companyId)
            ->pending()
            ->urgent()
            ->count();

        $overdueRecommendations = MrpRecommendation::where('company_id', $companyId)
            ->overdue()
            ->count();

        $byType = MrpRecommendation::where('company_id', $companyId)
            ->pending()
            ->selectRaw('recommendation_type, COUNT(*) as count')
            ->groupBy('recommendation_type')
            ->pluck('count', 'recommendation_type')
            ->toArray();

        return [
            'latest_run' => $latestRun ? [
                'id' => $latestRun->id,
                'run_number' => $latestRun->run_number,
                'completed_at' => $latestRun->completed_at,
                'recommendations_generated' => $latestRun->recommendations_generated,
            ] : null,
            'pending_recommendations' => $pendingRecommendations,
            'urgent_recommendations' => $urgentRecommendations,
            'overdue_recommendations' => $overdueRecommendations,
            'by_type' => $byType,
        ];
    }

    /**
     * Get products needing attention (below reorder point)
     */
    public function getProductsNeedingAttention(int $limit = 10): Collection
    {
        $companyId = Auth::user()->company_id;

        return Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('reorder_point')
            ->where('reorder_point', '>', 0)
            ->get()
            ->filter(function ($product) {
                return $product->isBelowReorderPoint();
            })
            ->take($limit)
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => $product->getTotalStock(),
                    'reorder_point' => $product->reorder_point,
                    'safety_stock' => $product->safety_stock,
                    'is_below_safety' => $product->isBelowSafetyStock(),
                ];
            });
    }

    /**
     * Get total warnings count from summary
     */
    protected function getTotalWarningsCount(): int
    {
        $total = 0;
        foreach ($this->warningsSummary as $summary) {
            $total += $summary['count'] ?? 1;
        }
        return $total;
    }

    /**
     * Get warnings summary for response
     */
    public function getWarningsSummary(): array
    {
        return array_values($this->warningsSummary);
    }
}
