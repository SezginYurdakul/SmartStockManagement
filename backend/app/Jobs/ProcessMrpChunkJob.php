<?php

namespace App\Jobs;

use App\Models\MrpRun;
use App\Models\Product;
use App\Services\MrpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Process MRP Chunk Job
 * 
 * Processes a chunk of products in parallel for better performance.
 * Used for large MRP runs to distribute work across multiple workers.
 */
class ProcessMrpChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes per chunk
    public $tries = 2;
    public $backoff = [60, 300];

    protected int $runId;
    protected array $productIds;
    protected array $params;

    /**
     * Create a new job instance.
     */
    public function __construct(int $runId, array $productIds, array $params)
    {
        $this->runId = $runId;
        $this->productIds = $productIds;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(MrpService $mrpService): void
    {
        $run = MrpRun::find($this->runId);

        if (!$run) {
            Log::error('ProcessMrpChunkJob: MRP run not found', ['run_id' => $this->runId]);
            return;
        }

        // Check if run was cancelled
        if ($run->status->value === 'cancelled') {
            Log::info('ProcessMrpChunkJob: MRP run was cancelled', ['run_id' => $this->runId]);
            return;
        }

        try {
            // Get products for this chunk
            $products = Product::whereIn('id', $this->productIds)
                ->where('company_id', $run->company_id)
                ->where('is_active', true)
                ->orderBy('low_level_code', 'asc')
                ->get();

            if ($products->isEmpty()) {
                Log::warning('ProcessMrpChunkJob: No products found for chunk', [
                    'run_id' => $this->runId,
                    'product_ids' => $this->productIds,
                ]);
                return;
            }

            // Process chunk
            $processed = $mrpService->processProductChunk($run, $products, $this->params);

            Log::info('ProcessMrpChunkJob: Chunk processed successfully', [
                'run_id' => $this->runId,
                'chunk_size' => count($this->productIds),
                'processed' => $processed,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessMrpChunkJob: Chunk processing failed', [
                'run_id' => $this->runId,
                'product_ids' => $this->productIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessMrpChunkJob: Job failed permanently', [
            'run_id' => $this->runId,
            'product_ids' => $this->productIds,
            'error' => $exception->getMessage(),
        ]);
    }
}
