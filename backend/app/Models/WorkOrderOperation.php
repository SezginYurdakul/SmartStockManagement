<?php

namespace App\Models;

use App\Enums\OperationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'routing_operation_id',
        'work_center_id',
        'operation_number',
        'name',
        'description',
        'status',
        'quantity_completed',
        'quantity_scrapped',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'actual_setup_time',
        'actual_run_time',
        'actual_cost',
        'notes',
        'started_by',
        'completed_by',
    ];

    protected $casts = [
        'status' => OperationStatus::class,
        'quantity_completed' => 'decimal:3',
        'quantity_scrapped' => 'decimal:3',
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'actual_setup_time' => 'decimal:2',
        'actual_run_time' => 'decimal:2',
        'actual_cost' => 'decimal:4',
    ];

    /**
     * Parent work order
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Source routing operation (if created from routing)
     */
    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class);
    }

    /**
     * Work center for this operation
     */
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    /**
     * User who started this operation
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * User who completed this operation
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Scope: Pending operations
     */
    public function scopePending($query)
    {
        return $query->where('status', OperationStatus::PENDING);
    }

    /**
     * Scope: In progress operations
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', OperationStatus::IN_PROGRESS);
    }

    /**
     * Scope: Completed operations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', OperationStatus::COMPLETED);
    }

    /**
     * Get total actual time in minutes
     */
    public function getTotalActualTimeAttribute(): float
    {
        return $this->actual_setup_time + $this->actual_run_time;
    }

    /**
     * Get duration in minutes (from start to end)
     */
    public function getDurationAttribute(): ?float
    {
        if (!$this->actual_start || !$this->actual_end) {
            return null;
        }

        return $this->actual_start->diffInMinutes($this->actual_end);
    }

    /**
     * Check if operation can be started
     */
    public function canStart(): bool
    {
        return $this->status->canStart();
    }

    /**
     * Check if operation can be completed
     */
    public function canComplete(): bool
    {
        return $this->status->canComplete();
    }

    /**
     * Check if operation is in final state
     */
    public function isFinished(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Get efficiency compared to planned time
     */
    public function getEfficiencyAttribute(): ?float
    {
        $routingOp = $this->routingOperation;

        if (!$routingOp || $this->total_actual_time == 0) {
            return null;
        }

        $plannedTime = $routingOp->setup_time +
                       ($routingOp->run_time_per_unit * $this->quantity_completed);

        if ($plannedTime == 0) {
            return null;
        }

        return round(($plannedTime / $this->total_actual_time) * 100, 2);
    }
}
