<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToCompany;

    /**
     * The table associated with the model.
     */
    protected $table = 'stock';

    /**
     * Stock statuses
     */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Quality statuses
     */
    public const QUALITY_AVAILABLE = 'available';
    public const QUALITY_PENDING_INSPECTION = 'pending_inspection';
    public const QUALITY_ON_HOLD = 'on_hold';
    public const QUALITY_CONDITIONAL = 'conditional';
    public const QUALITY_REJECTED = 'rejected';
    public const QUALITY_QUARANTINE = 'quarantine';

    /**
     * Quality status labels
     */
    public const QUALITY_STATUSES = [
        self::QUALITY_AVAILABLE => 'Available',
        self::QUALITY_PENDING_INSPECTION => 'Pending Inspection',
        self::QUALITY_ON_HOLD => 'On Hold',
        self::QUALITY_CONDITIONAL => 'Conditional',
        self::QUALITY_REJECTED => 'Rejected',
        self::QUALITY_QUARANTINE => 'Quarantine',
    ];

    /**
     * Operation types for restriction checking
     */
    public const OPERATION_TRANSFER = 'transfer';
    public const OPERATION_SALE = 'sale';
    public const OPERATION_PRODUCTION = 'production';
    public const OPERATION_BUNDLE = 'bundle';
    public const OPERATION_ADJUSTMENT = 'adjustment';

    /**
     * Default allowed operations by quality status
     */
    public const QUALITY_OPERATIONS = [
        self::QUALITY_AVAILABLE => [
            self::OPERATION_TRANSFER => true,
            self::OPERATION_SALE => true,
            self::OPERATION_PRODUCTION => true,
            self::OPERATION_BUNDLE => true,
            self::OPERATION_ADJUSTMENT => true,
        ],
        self::QUALITY_PENDING_INSPECTION => [
            self::OPERATION_TRANSFER => true,  // Can transfer to QC area
            self::OPERATION_SALE => false,
            self::OPERATION_PRODUCTION => false,
            self::OPERATION_BUNDLE => false,
            self::OPERATION_ADJUSTMENT => true,
        ],
        self::QUALITY_ON_HOLD => [
            self::OPERATION_TRANSFER => false,
            self::OPERATION_SALE => false,
            self::OPERATION_PRODUCTION => false,
            self::OPERATION_BUNDLE => false,
            self::OPERATION_ADJUSTMENT => true,  // For corrections
        ],
        self::QUALITY_CONDITIONAL => [
            self::OPERATION_TRANSFER => true,
            self::OPERATION_SALE => false,      // May be allowed with restrictions
            self::OPERATION_PRODUCTION => true,  // With specific use cases
            self::OPERATION_BUNDLE => false,
            self::OPERATION_ADJUSTMENT => true,
        ],
        self::QUALITY_REJECTED => [
            self::OPERATION_TRANSFER => true,  // Can transfer to rejection zone
            self::OPERATION_SALE => false,
            self::OPERATION_PRODUCTION => false,
            self::OPERATION_BUNDLE => false,
            self::OPERATION_ADJUSTMENT => true,
        ],
        self::QUALITY_QUARANTINE => [
            self::OPERATION_TRANSFER => true,  // Can transfer to/from quarantine
            self::OPERATION_SALE => false,
            self::OPERATION_PRODUCTION => false,
            self::OPERATION_BUNDLE => false,
            self::OPERATION_ADJUSTMENT => true,
        ],
    ];

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'lot_number',
        'serial_number',
        'quantity_on_hand',
        'quantity_reserved',
        'unit_cost',
        'expiry_date',
        'received_date',
        'status',
        'notes',
        // Quality control fields
        'quality_status',
        'hold_reason',
        'hold_until',
        'quality_restrictions',
        'quality_hold_by',
        'quality_hold_at',
        'quality_reference_type',
        'quality_reference_id',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'quantity_reserved' => 'decimal:3',
        'quantity_available' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'expiry_date' => 'date',
        'received_date' => 'date',
        'hold_until' => 'datetime',
        'quality_restrictions' => 'array',
        'quality_hold_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who placed the quality hold
     */
    public function qualityHoldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_hold_by');
    }

    /**
     * Get the quality reference (polymorphic - Inspection, NCR, etc.)
     */
    public function qualityReference()
    {
        return $this->morphTo('quality_reference');
    }

    /**
     * Scope to filter available stock
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
            ->where('quantity_available', '>', 0);
    }

    /**
     * Scope to filter by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by warehouse
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by lot number
     */
    public function scopeWithLot($query, string $lotNumber)
    {
        return $query->where('lot_number', $lotNumber);
    }

    /**
     * Scope to filter expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    /**
     * Scope to filter expired stock
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    /**
     * Scope to filter low stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->whereColumn('stock.quantity_available', '<=', 'products.low_stock_threshold');
        });
    }

    /**
     * Check if stock is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if stock is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }

    /**
     * Reserve stock quantity
     */
    public function reserve(float $quantity): bool
    {
        if ($this->quantity_available < $quantity) {
            return false;
        }

        $this->increment('quantity_reserved', $quantity);
        return true;
    }

    /**
     * Release reserved stock
     */
    public function releaseReservation(float $quantity): bool
    {
        if ($this->quantity_reserved < $quantity) {
            return false;
        }

        $this->decrement('quantity_reserved', $quantity);
        return true;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_QUARANTINE => 'Quarantine',
            self::STATUS_DAMAGED => 'Damaged',
            self::STATUS_EXPIRED => 'Expired',
            default => $this->status,
        };
    }

    /**
     * Get quality status label
     */
    public function getQualityStatusLabelAttribute(): string
    {
        return self::QUALITY_STATUSES[$this->quality_status] ?? $this->quality_status;
    }

    /**
     * Scope to filter by quality status
     */
    public function scopeWithQualityStatus($query, string $qualityStatus)
    {
        return $query->where('quality_status', $qualityStatus);
    }

    /**
     * Scope to filter quality available stock (not held, not rejected, not quarantine)
     */
    public function scopeQualityAvailable($query)
    {
        return $query->where('quality_status', self::QUALITY_AVAILABLE);
    }

    /**
     * Scope to filter stock on quality hold
     */
    public function scopeOnQualityHold($query)
    {
        return $query->whereIn('quality_status', [
            self::QUALITY_ON_HOLD,
            self::QUALITY_PENDING_INSPECTION,
            self::QUALITY_QUARANTINE,
        ]);
    }

    /**
     * Scope to filter rejected stock
     */
    public function scopeQualityRejected($query)
    {
        return $query->where('quality_status', self::QUALITY_REJECTED);
    }

    /**
     * Scope to filter stock usable for operations (available or conditional)
     */
    public function scopeUsable($query)
    {
        return $query->whereIn('quality_status', [
            self::QUALITY_AVAILABLE,
            self::QUALITY_CONDITIONAL,
        ]);
    }

    /**
     * Check if operation is allowed based on quality status
     */
    public function isOperationAllowed(string $operation): bool
    {
        $qualityStatus = $this->quality_status ?? self::QUALITY_AVAILABLE;

        // Check base permissions from quality status
        $baseAllowed = self::QUALITY_OPERATIONS[$qualityStatus][$operation] ?? false;

        if (!$baseAllowed) {
            return false;
        }

        // For conditional status, check specific restrictions
        if ($qualityStatus === self::QUALITY_CONDITIONAL && $this->quality_restrictions) {
            $restrictions = $this->quality_restrictions;

            // If operation is explicitly restricted
            if (isset($restrictions['blocked_operations']) &&
                in_array($operation, $restrictions['blocked_operations'])) {
                return false;
            }

            // If only specific operations are allowed
            if (isset($restrictions['allowed_operations']) &&
                !in_array($operation, $restrictions['allowed_operations'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get blocked operations for current quality status
     */
    public function getBlockedOperations(): array
    {
        $qualityStatus = $this->quality_status ?? self::QUALITY_AVAILABLE;
        $blocked = [];

        foreach (self::QUALITY_OPERATIONS[$qualityStatus] ?? [] as $operation => $allowed) {
            if (!$allowed) {
                $blocked[] = $operation;
            }
        }

        // Add any conditional restrictions
        if ($qualityStatus === self::QUALITY_CONDITIONAL && $this->quality_restrictions) {
            if (isset($this->quality_restrictions['blocked_operations'])) {
                $blocked = array_unique(array_merge($blocked, $this->quality_restrictions['blocked_operations']));
            }
        }

        return $blocked;
    }

    /**
     * Check if stock is on any kind of quality hold
     */
    public function isOnQualityHold(): bool
    {
        return in_array($this->quality_status, [
            self::QUALITY_ON_HOLD,
            self::QUALITY_PENDING_INSPECTION,
            self::QUALITY_QUARANTINE,
        ]);
    }

    /**
     * Check if stock is usable (available or conditional)
     */
    public function isQualityUsable(): bool
    {
        return in_array($this->quality_status, [
            self::QUALITY_AVAILABLE,
            self::QUALITY_CONDITIONAL,
        ]);
    }

    /**
     * Check if stock is rejected
     */
    public function isQualityRejected(): bool
    {
        return $this->quality_status === self::QUALITY_REJECTED;
    }

    /**
     * Check if hold has expired
     */
    public function isHoldExpired(): bool
    {
        return $this->hold_until && $this->hold_until->isPast();
    }

    /**
     * Place quality hold on stock
     */
    public function placeQualityHold(
        string $status,
        ?string $reason = null,
        ?\DateTimeInterface $holdUntil = null,
        ?array $restrictions = null,
        ?int $holdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): self {
        $this->update([
            'quality_status' => $status,
            'hold_reason' => $reason,
            'hold_until' => $holdUntil,
            'quality_restrictions' => $restrictions,
            'quality_hold_by' => $holdBy,
            'quality_hold_at' => now(),
            'quality_reference_type' => $referenceType,
            'quality_reference_id' => $referenceId,
        ]);

        return $this;
    }

    /**
     * Release quality hold
     */
    public function releaseQualityHold(): self
    {
        $this->update([
            'quality_status' => self::QUALITY_AVAILABLE,
            'hold_reason' => null,
            'hold_until' => null,
            'quality_restrictions' => null,
            'quality_hold_by' => null,
            'quality_hold_at' => null,
            'quality_reference_type' => null,
            'quality_reference_id' => null,
        ]);

        return $this;
    }

    /**
     * Set conditional quality status with restrictions
     */
    public function setConditionalStatus(array $restrictions, ?string $reason = null, ?int $userId = null): self
    {
        $this->update([
            'quality_status' => self::QUALITY_CONDITIONAL,
            'hold_reason' => $reason,
            'quality_restrictions' => $restrictions,
            'quality_hold_by' => $userId,
            'quality_hold_at' => now(),
        ]);

        return $this;
    }
}
