<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogAuditEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Retry 3 times
    public $backoff = [10, 30, 60]; // Wait 10s, 30s, 60s between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $auditData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            AuditLog::create($this->auditData);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'audit_data' => $this->auditData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Audit log job failed after all retries', [
            'audit_data' => $this->auditData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
