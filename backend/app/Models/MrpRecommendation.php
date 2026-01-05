<?php

namespace App\Models;

use App\Enums\MrpRecommendationType;
use App\Enums\MrpRecommendationStatus;
use App\Enums\MrpPriority;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpRecommendation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'mrp_run_id',
        'product_id',
        'warehouse_id',
        'recommendation_type',
        'required_date',
        'suggested_date',
        'due_date',
        'gross_requirement',
        'net_requirement',
        'suggested_quantity',
        'current_stock',
        'projected_stock',
        'demand_source_type',
        'demand_source_id',
        'priority',
        'is_urgent',
        'urgency_reason',
        'status',
        'actioned_at',
        'actioned_by',
        'action_reference_type',
        'action_reference_id',
        'action_notes',
        'calculation_details',
    ];

    protected $casts = [
        'recommendation_type' => MrpRecommendationType::class,
        'required_date' => 'date',
        'suggested_date' => 'date',
        'due_date' => 'date',
        'gross_requirement' => 'decimal:4',
        'net_requirement' => 'decimal:4',
        'suggested_quantity' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'projected_stock' => 'decimal:4',
        'priority' => MrpPriority::class,
        'is_urgent' => 'boolean',
        'status' => MrpRecommendationStatus::class,
        'actioned_at' => 'datetime',
        'calculation_details' => 'array',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mrpRun(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function actionedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopePending($query)
    {
        return $query->where('status', MrpRecommendationStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', MrpRecommendationStatus::APPROVED);
    }

    public function scopeActioned($query)
    {
        return $query->where('status', MrpRecommendationStatus::ACTIONED);
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', [
            MrpRecommendationStatus::PENDING,
            MrpRecommendationStatus::APPROVED,
        ]);
    }

    public function scopeUrgent($query)
    {
        return $query->where('is_urgent', true);
    }

    public function scopeOfType($query, MrpRecommendationType $type)
    {
        return $query->where('recommendation_type', $type);
    }

    public function scopePurchaseOrders($query)
    {
        return $query->where('recommendation_type', MrpRecommendationType::PURCHASE_ORDER);
    }

    public function scopeWorkOrders($query)
    {
        return $query->where('recommendation_type', MrpRecommendationType::WORK_ORDER);
    }

    public function scopeByPriority($query)
    {
        return $query->orderByRaw("
            CASE priority
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END
        ");
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('required_date', '<=', now()->addDays($days));
    }

    public function scopeOverdue($query)
    {
        return $query->where('required_date', '<', today())
            ->whereIn('status', [
                MrpRecommendationStatus::PENDING,
                MrpRecommendationStatus::APPROVED,
            ]);
    }

    // =========================================
    // Status Management
    // =========================================

    public function approve(): bool
    {
        if (!$this->status->canApprove()) {
            return false;
        }

        return $this->update([
            'status' => MrpRecommendationStatus::APPROVED,
        ]);
    }

    public function reject(?string $notes = null): bool
    {
        if (!$this->status->canReject()) {
            return false;
        }

        return $this->update([
            'status' => MrpRecommendationStatus::REJECTED,
            'action_notes' => $notes,
            'actioned_at' => now(),
            'actioned_by' => auth()->id(),
        ]);
    }

    public function markAsActioned(
        string $referenceType,
        int $referenceId,
        ?string $notes = null
    ): bool {
        if (!$this->status->canAction()) {
            return false;
        }

        return $this->update([
            'status' => MrpRecommendationStatus::ACTIONED,
            'action_reference_type' => $referenceType,
            'action_reference_id' => $referenceId,
            'action_notes' => $notes,
            'actioned_at' => now(),
            'actioned_by' => auth()->id(),
        ]);
    }

    public function expire(): bool
    {
        if ($this->status->isFinal()) {
            return false;
        }

        return $this->update([
            'status' => MrpRecommendationStatus::EXPIRED,
        ]);
    }

    // =========================================
    // Computed Properties
    // =========================================

    public function getDaysUntilRequiredAttribute(): int
    {
        return today()->diffInDays($this->required_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->required_date < today() && !$this->status->isFinal();
    }

    public function getActionReferenceAttribute(): ?Model
    {
        if (!$this->action_reference_type || !$this->action_reference_id) {
            return null;
        }

        return match ($this->action_reference_type) {
            'purchase_order' => PurchaseOrder::find($this->action_reference_id),
            'work_order' => WorkOrder::find($this->action_reference_id),
            default => null,
        };
    }

    public function getDemandSourceAttribute(): ?Model
    {
        if (!$this->demand_source_type || !$this->demand_source_id) {
            return null;
        }

        return match ($this->demand_source_type) {
            'work_order' => WorkOrder::find($this->demand_source_id),
            'sales_order' => SalesOrder::find($this->demand_source_id),
            default => null,
        };
    }

    // =========================================
    // Helpers
    // =========================================

    public function getSummary(): string
    {
        $typeLabel = $this->recommendation_type->label();
        $quantity = number_format($this->suggested_quantity, 2);
        $productName = $this->product?->name ?? 'Unknown';
        $date = $this->suggested_date->format('Y-m-d');

        return "{$typeLabel}: {$quantity} {$productName} by {$date}";
    }

    public function toActionArray(): array
    {
        return [
            'recommendation_id' => $this->id,
            'type' => $this->recommendation_type->value,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity' => $this->suggested_quantity,
            'required_date' => $this->required_date->toDateString(),
            'suggested_date' => $this->suggested_date->toDateString(),
        ];
    }
}
