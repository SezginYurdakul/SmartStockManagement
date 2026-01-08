<?php

namespace App\Models;

use App\Enums\MrpRunStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MrpRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'run_number',
        'name',
        'planning_horizon_start',
        'planning_horizon_end',
        'include_safety_stock',
        'respect_lead_times',
        'consider_wip',
        'net_change',
        'product_filters',
        'warehouse_filters',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'products_processed',
        'recommendations_generated',
        'warnings_count',
        'warnings_summary',
        'created_by',
    ];

    protected $casts = [
        'planning_horizon_start' => 'date',
        'planning_horizon_end' => 'date',
        'include_safety_stock' => 'boolean',
        'respect_lead_times' => 'boolean',
        'consider_wip' => 'boolean',
        'net_change' => 'boolean',
        'product_filters' => 'array',
        'warehouse_filters' => 'array',
        'status' => MrpRunStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'products_processed' => 'integer',
        'recommendations_generated' => 'integer',
        'warnings_count' => 'integer',
        'warnings_summary' => 'array',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(MrpRecommendation::class);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeStatus($query, MrpRunStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MrpRunStatus::COMPLETED);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    // =========================================
    // Status Management
    // =========================================

    public function markAsRunning(): bool
    {
        if ($this->status !== MrpRunStatus::PENDING) {
            return false;
        }

        return $this->update([
            'status' => MrpRunStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(int $productsProcessed, int $recommendationsGenerated, int $warnings = 0, ?array $warningsSummary = null): bool
    {
        if ($this->status !== MrpRunStatus::RUNNING) {
            return false;
        }

        return $this->update([
            'status' => MrpRunStatus::COMPLETED,
            'completed_at' => now(),
            'products_processed' => $productsProcessed,
            'recommendations_generated' => $recommendationsGenerated,
            'warnings_count' => $warnings,
            'warnings_summary' => $warningsSummary,
        ]);
    }

    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => MrpRunStatus::FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsCancelled(): bool
    {
        if (!$this->status->canCancel()) {
            return false;
        }

        return $this->update([
            'status' => MrpRunStatus::CANCELLED,
            'completed_at' => now(),
        ]);
    }

    // =========================================
    // Computed Properties
    // =========================================

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $seconds = $this->duration;
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return "{$minutes}m {$remainingSeconds}s";
    }

    public function getPlanningHorizonDaysAttribute(): int
    {
        return $this->planning_horizon_start->diffInDays($this->planning_horizon_end);
    }

    // =========================================
    // Helpers
    // =========================================

    public static function generateRunNumber(int $companyId): string
    {
        $date = now()->format('Ymd');
        $companyIdPadded = str_pad($companyId, 3, '0', STR_PAD_LEFT);
        $prefix = "MRP-{$date}-{$companyIdPadded}-";
        
        $lastRun = static::where('company_id', $companyId)
            ->where('run_number', 'like', "{$prefix}%")
            ->whereDate('created_at', today())
            ->orderByRaw("CAST(SUBSTRING(run_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        $sequence = 1;
        if ($lastRun && preg_match('/(\d+)$/', $lastRun->run_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function getPendingRecommendationsCount(): int
    {
        return $this->recommendations()
            ->where('status', 'pending')
            ->count();
    }

    public function getActionedRecommendationsCount(): int
    {
        return $this->recommendations()
            ->where('status', 'actioned')
            ->count();
    }
}
