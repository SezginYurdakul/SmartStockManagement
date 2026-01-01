<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutingOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'routing_id',
        'work_center_id',
        'operation_number',
        'name',
        'description',
        'setup_time',
        'run_time_per_unit',
        'queue_time',
        'move_time',
        'is_subcontracted',
        'subcontractor_id',
        'subcontract_cost',
        'instructions',
        'settings',
    ];

    protected $casts = [
        'setup_time' => 'decimal:2',
        'run_time_per_unit' => 'decimal:4',
        'queue_time' => 'decimal:2',
        'move_time' => 'decimal:2',
        'is_subcontracted' => 'boolean',
        'subcontract_cost' => 'decimal:4',
        'settings' => 'array',
    ];

    /**
     * Parent routing
     */
    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    /**
     * Work center for this operation
     */
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    /**
     * Subcontractor (if subcontracted)
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'subcontractor_id');
    }

    /**
     * Work order operations created from this template
     */
    public function workOrderOperations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class);
    }

    /**
     * Get total time for this operation (per unit)
     */
    public function getTotalTimePerUnitAttribute(): float
    {
        return $this->setup_time + $this->run_time_per_unit + $this->queue_time + $this->move_time;
    }

    /**
     * Calculate operation time for a given quantity
     */
    public function calculateTime(float $quantity): float
    {
        return $this->setup_time + ($this->run_time_per_unit * $quantity) + $this->queue_time + $this->move_time;
    }

    /**
     * Calculate operation cost for a given quantity
     */
    public function calculateCost(float $quantity): float
    {
        if ($this->is_subcontracted) {
            return ($this->subcontract_cost ?? 0) * $quantity;
        }

        $hours = $this->calculateTime($quantity) / 60;
        return $hours * ($this->workCenter->cost_per_hour ?? 0);
    }
}
