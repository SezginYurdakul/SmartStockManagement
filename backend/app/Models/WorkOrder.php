<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderPriority;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'work_order_number',
        'product_id',
        'bom_id',
        'routing_id',
        'quantity_ordered',
        'quantity_completed',
        'quantity_scrapped',
        'uom_id',
        'warehouse_id',
        'status',
        'priority',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'estimated_cost',
        'actual_cost',
        'notes',
        'internal_notes',
        'meta_data',
        'created_by',
        'approved_by',
        'approved_at',
        'released_by',
        'released_at',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'status' => WorkOrderStatus::class,
        'priority' => WorkOrderPriority::class,
        'quantity_ordered' => 'decimal:3',
        'quantity_completed' => 'decimal:3',
        'quantity_scrapped' => 'decimal:3',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
        'planned_start_date' => 'datetime',
        'planned_end_date' => 'datetime',
        'actual_start_date' => 'datetime',
        'actual_end_date' => 'datetime',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta_data' => 'array',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Product being manufactured
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * BOM used for this work order
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * Routing used for this work order
     */
    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    /**
     * Unit of measure
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Destination warehouse for finished goods
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Releaser
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * Completer
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Operations for this work order
     */
    public function operations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class)->orderBy('operation_number');
    }

    /**
     * Materials required for this work order
     */
    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, WorkOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Active work orders (not completed or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            WorkOrderStatus::COMPLETED,
            WorkOrderStatus::CANCELLED,
        ]);
    }

    /**
     * Scope: In progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', WorkOrderStatus::IN_PROGRESS);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, WorkOrderPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: For a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Search by number
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('work_order_number', 'ilike', "%{$term}%");
    }

    /**
     * Scope: Planned for date range
     */
    public function scopePlannedBetween($query, \DateTimeInterface $start, \DateTimeInterface $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('planned_start_date', [$start, $end])
              ->orWhereBetween('planned_end_date', [$start, $end]);
        });
    }

    /**
     * Get remaining quantity to complete
     */
    public function getRemainingQuantityAttribute(): float
    {
        return $this->quantity_ordered - $this->quantity_completed - $this->quantity_scrapped;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->quantity_ordered == 0) {
            return 0;
        }

        return round(($this->quantity_completed / $this->quantity_ordered) * 100, 2);
    }

    /**
     * Get operations completion percentage
     */
    public function getOperationsProgressAttribute(): float
    {
        $total = $this->operations()->count();

        if ($total === 0) {
            return 0;
        }

        $completed = $this->operations()
            ->whereIn('status', ['completed', 'skipped'])
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Check if work order can be edited
     */
    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if work order can be released
     */
    public function canRelease(): bool
    {
        return $this->status->canRelease();
    }

    /**
     * Check if work order can be started
     */
    public function canStart(): bool
    {
        return $this->status->canStart();
    }

    /**
     * Check if work order can be completed
     */
    public function canComplete(): bool
    {
        return $this->status->canComplete();
    }

    /**
     * Check if work order can be cancelled
     */
    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    /**
     * Check if work order can issue materials
     */
    public function canIssueMaterials(): bool
    {
        return $this->status->canIssueMaterials();
    }

    /**
     * Check if work order can receive finished goods
     */
    public function canReceiveFinishedGoods(): bool
    {
        return $this->status->canReceiveFinishedGoods();
    }

    /**
     * Check if all operations are completed
     */
    public function allOperationsCompleted(): bool
    {
        return $this->operations()
            ->whereNotIn('status', ['completed', 'skipped'])
            ->count() === 0;
    }
}
