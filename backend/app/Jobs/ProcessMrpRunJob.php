<?php

namespace App\Jobs;

use App\Models\MrpRun;
use App\Services\MrpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process MRP Run Job
 * 
 * Processes MRP calculation in the background for better user experience
 * and to avoid HTTP timeout issues with large product lists.
 */
class ProcessMrpRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout for large MRP runs
    public $tries = 1; // Don't retry - MRP runs should be idempotent
    public $backoff = [60, 300]; // Wait 1 min, then 5 min before retry

    protected int $runId;
    protected array $params;

    /**
     * Create a new job instance.
     */
    public function __construct(int $runId, array $params)
    {
        $this->runId = $runId;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(MrpService $mrpService): void
    {
        $run = MrpRun::find($this->runId);

        if (!$run) {
            Log::error('ProcessMrpRunJob: MRP run not found', ['run_id' => $this->runId]);
            return;
        }

        // Check if run was cancelled
        if ($run->status->value === 'cancelled') {
            Log::info('ProcessMrpRunJob: MRP run was cancelled', ['run_id' => $this->runId]);
            return;
        }

        try {
            // Set the run context for the service
            // The service will use the existing run record
            $mrpService->processExistingRun($run, $this->params);

            Log::info('ProcessMrpRunJob: MRP run completed successfully', [
                'run_id' => $this->runId,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessMrpRunJob: MRP run failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark run as failed
            $run->markAsFailed($e->getMessage());

            // Re-throw to trigger failed job handling
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $run = MrpRun::find($this->runId);

        if ($run && $run->status->value !== 'completed') {
            $run->markAsFailed('Job failed: ' . $exception->getMessage());
        }

        Log::error('ProcessMrpRunJob: Job failed permanently', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);
    }
}
