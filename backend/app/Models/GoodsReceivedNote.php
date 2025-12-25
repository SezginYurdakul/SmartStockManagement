<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceivedNote extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_INSPECTION = 'pending_inspection';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'grn_number',
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'received_date',
        'delivery_note_number',
        'delivery_note_date',
        'invoice_number',
        'invoice_date',
        'status',
        'requires_inspection',
        'inspected_by',
        'inspected_at',
        'inspection_notes',
        'notes',
        'meta_data',
        'received_by',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'delivery_note_date' => 'date',
        'invoice_date' => 'date',
        'requires_inspection' => 'boolean',
        'inspected_at' => 'datetime',
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
     * Purchase order relationship
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * GRN items
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class)->orderBy('line_number');
    }

    /**
     * Received by user
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Inspected by user
     */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * Creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_INSPECTION,
        ]);
    }

    /**
     * Check if can be completed
     */
    public function canBeCompleted(): bool
    {
        if ($this->requires_inspection) {
            return $this->status === self::STATUS_INSPECTED;
        }

        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_INSPECTION,
        ]);
    }

    /**
     * Get total quantity received
     */
    public function getTotalQuantityReceivedAttribute(): float
    {
        return $this->items->sum('quantity_received');
    }

    /**
     * Get total quantity accepted
     */
    public function getTotalQuantityAcceptedAttribute(): float
    {
        return $this->items->sum('quantity_accepted');
    }

    /**
     * Get total quantity rejected
     */
    public function getTotalQuantityRejectedAttribute(): float
    {
        return $this->items->sum('quantity_rejected');
    }

    /**
     * Get total cost
     */
    public function getTotalCostAttribute(): float
    {
        return $this->items->sum('total_cost');
    }

    /**
     * Scope by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending inspection
     */
    public function scopePendingInspection($query)
    {
        return $query->where('status', self::STATUS_PENDING_INSPECTION);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('received_date', [$from, $to]);
    }
}
