<?php

namespace App\Models;

use App\Enums\PoStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'order_number',
        'supplier_id',
        'mrp_recommendation_id',
        'warehouse_id',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'status',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'other_charges',
        'total_amount',
        'payment_terms',
        'payment_due_days',
        'shipping_method',
        'shipping_address',
        'notes',
        'internal_notes',
        'meta_data',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_due_days' => 'integer',
        'meta_data' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Supplier relationship
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Warehouse relationship
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Order items
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_number');
    }

    /**
     * Goods Received Notes
     */
    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater relationship
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Approver relationship
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * MRP Recommendation that generated this purchase order
     */
    public function mrpRecommendation(): BelongsTo
    {
        return $this->belongsTo(MrpRecommendation::class);
    }

    /**
     * Get status as Enum
     */
    public function getStatusEnumAttribute(): ?PoStatus
    {
        return $this->status ? PoStatus::tryFrom($this->status) : null;
    }

    /**
     * Check if order can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status_enum?->canEdit() ?? false;
    }

    /**
     * Check if order can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status_enum?->requiresApproval() ?? false;
    }

    /**
     * Check if order can be sent
     */
    public function canBeSent(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if order can receive goods
     */
    public function canReceiveGoods(): bool
    {
        return $this->status_enum?->canReceive() ?? false;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status_enum?->canCancel() ?? false;
    }

    /**
     * Get total quantity ordered
     */
    public function getTotalQuantityOrderedAttribute(): float
    {
        return $this->items->sum('quantity_ordered');
    }

    /**
     * Get total quantity received
     */
    public function getTotalQuantityReceivedAttribute(): float
    {
        return $this->items->sum('quantity_received');
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingQuantityAttribute(): float
    {
        return $this->total_quantity_ordered - $this->total_quantity_received;
    }

    /**
     * Get receiving progress percentage
     */
    public function getReceivingProgressAttribute(): float
    {
        if ($this->total_quantity_ordered == 0) {
            return 0;
        }

        return round(($this->total_quantity_received / $this->total_quantity_ordered) * 100, 2);
    }

    /**
     * Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');
        $taxAmount = $this->items->sum('tax_amount');

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total_amount = $subtotal - $this->discount_amount + $taxAmount + $this->shipping_cost + $this->other_charges;
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending orders
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_SENT,
            self::STATUS_PARTIALLY_RECEIVED,
        ]);
    }

    /**
     * Scope: Filter by supplier
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('order_date', [$from, $to]);
    }

    /**
     * Scope: Overdue orders
     */
    public function scopeOverdue($query)
    {
        return $query->where('expected_delivery_date', '<', now())
            ->whereIn('status', [
                self::STATUS_SENT,
                self::STATUS_PARTIALLY_RECEIVED,
            ]);
    }
}
