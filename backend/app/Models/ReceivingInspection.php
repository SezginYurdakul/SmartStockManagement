<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivingInspection extends Model
{
    use HasFactory, BelongsToCompany;

    // Result constants
    public const RESULT_PENDING = 'pending';
    public const RESULT_PASSED = 'passed';
    public const RESULT_FAILED = 'failed';
    public const RESULT_PARTIAL = 'partial';
    public const RESULT_ON_HOLD = 'on_hold';

    // Disposition constants
    public const DISPOSITION_ACCEPT = 'accept';
    public const DISPOSITION_REJECT = 'reject';
    public const DISPOSITION_REWORK = 'rework';
    public const DISPOSITION_RETURN = 'return_to_supplier';
    public const DISPOSITION_USE_AS_IS = 'use_as_is';
    public const DISPOSITION_PENDING = 'pending';

    protected $fillable = [
        'company_id',
        'goods_received_note_id',
        'grn_item_id',
        'product_id',
        'acceptance_rule_id',
        'inspection_number',
        'lot_number',
        'batch_number',
        'quantity_received',
        'quantity_inspected',
        'quantity_passed',
        'quantity_failed',
        'quantity_on_hold',
        'result',
        'disposition',
        'inspection_data',
        'failure_reason',
        'notes',
        'inspected_by',
        'inspected_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'quantity_inspected' => 'decimal:4',
        'quantity_passed' => 'decimal:4',
        'quantity_failed' => 'decimal:4',
        'quantity_on_hold' => 'decimal:4',
        'inspection_data' => 'array',
        'inspected_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Result labels
     */
    public const RESULTS = [
        self::RESULT_PENDING => 'Pending',
        self::RESULT_PASSED => 'Passed',
        self::RESULT_FAILED => 'Failed',
        self::RESULT_PARTIAL => 'Partial',
        self::RESULT_ON_HOLD => 'On Hold',
    ];

    /**
     * Disposition labels
     */
    public const DISPOSITIONS = [
        self::DISPOSITION_ACCEPT => 'Accept',
        self::DISPOSITION_REJECT => 'Reject',
        self::DISPOSITION_REWORK => 'Rework',
        self::DISPOSITION_RETURN => 'Return to Supplier',
        self::DISPOSITION_USE_AS_IS => 'Use As Is',
        self::DISPOSITION_PENDING => 'Pending Decision',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * GRN relationship
     */
    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    /**
     * GRN Item relationship
     */
    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNoteItem::class, 'grn_item_id');
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Acceptance rule used
     */
    public function acceptanceRule(): BelongsTo
    {
        return $this->belongsTo(AcceptanceRule::class);
    }

    /**
     * Inspector relationship
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * Approver relationship
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * NCRs related to this inspection
     */
    public function nonConformanceReports(): HasMany
    {
        return $this->hasMany(NonConformanceReport::class, 'receiving_inspection_id');
    }

    /**
     * Get pass rate percentage
     */
    public function getPassRateAttribute(): float
    {
        if ($this->quantity_inspected <= 0) {
            return 0;
        }

        return round(($this->quantity_passed / $this->quantity_inspected) * 100, 2);
    }

    /**
     * Get result label
     */
    public function getResultLabelAttribute(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    /**
     * Get disposition label
     */
    public function getDispositionLabelAttribute(): string
    {
        return self::DISPOSITIONS[$this->disposition] ?? $this->disposition;
    }

    /**
     * Check if inspection is complete
     */
    public function isComplete(): bool
    {
        return $this->result !== self::RESULT_PENDING;
    }

    /**
     * Check if requires NCR
     */
    public function requiresNcr(): bool
    {
        return $this->quantity_failed > 0 || $this->result === self::RESULT_FAILED;
    }

    /**
     * Scope: Pending inspections
     */
    public function scopePending($query)
    {
        return $query->where('result', self::RESULT_PENDING);
    }

    /**
     * Scope: Completed inspections
     */
    public function scopeCompleted($query)
    {
        return $query->where('result', '!=', self::RESULT_PENDING);
    }

    /**
     * Scope: Failed inspections
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('result', [self::RESULT_FAILED, self::RESULT_PARTIAL]);
    }

    /**
     * Scope: By product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('inspected_at', [$from, $to]);
    }
}
