<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonConformanceReport extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_PENDING_DISPOSITION = 'pending_disposition';
    public const STATUS_DISPOSITION_APPROVED = 'disposition_approved';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // Severity constants
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MAJOR = 'major';
    public const SEVERITY_CRITICAL = 'critical';

    // Source type constants
    public const SOURCE_RECEIVING = 'receiving';
    public const SOURCE_PRODUCTION = 'production';
    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_CUSTOMER = 'customer';

    // Disposition constants
    public const DISPOSITION_PENDING = 'pending';
    public const DISPOSITION_USE_AS_IS = 'use_as_is';
    public const DISPOSITION_REWORK = 'rework';
    public const DISPOSITION_SCRAP = 'scrap';
    public const DISPOSITION_RETURN = 'return_to_supplier';
    public const DISPOSITION_SORT = 'sort_and_use';
    public const DISPOSITION_REJECT = 'reject';

    protected $fillable = [
        'company_id',
        'source_type',
        'receiving_inspection_id',
        'ncr_number',
        'title',
        'description',
        'product_id',
        'supplier_id',
        'lot_number',
        'batch_number',
        'quantity_affected',
        'unit_of_measure',
        'severity',
        'priority',
        'defect_type',
        'root_cause',
        'disposition',
        'disposition_reason',
        'cost_impact',
        'cost_currency',
        'status',
        'attachments',
        'reported_by',
        'reported_at',
        'reviewed_by',
        'reviewed_at',
        'disposition_by',
        'disposition_at',
        'closed_by',
        'closed_at',
        'closure_notes',
    ];

    protected $casts = [
        'quantity_affected' => 'decimal:4',
        'cost_impact' => 'decimal:2',
        'attachments' => 'array',
        'reported_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'disposition_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Status labels
     */
    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_UNDER_REVIEW => 'Under Review',
        self::STATUS_PENDING_DISPOSITION => 'Pending Disposition',
        self::STATUS_DISPOSITION_APPROVED => 'Disposition Approved',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Severity labels
     */
    public const SEVERITIES = [
        self::SEVERITY_MINOR => 'Minor',
        self::SEVERITY_MAJOR => 'Major',
        self::SEVERITY_CRITICAL => 'Critical',
    ];

    /**
     * Defect type labels
     */
    public const DEFECT_TYPES = [
        'dimensional' => 'Dimensional',
        'visual' => 'Visual',
        'functional' => 'Functional',
        'documentation' => 'Documentation',
        'packaging' => 'Packaging',
        'contamination' => 'Contamination',
        'wrong_item' => 'Wrong Item',
        'quantity_short' => 'Quantity Short',
        'quantity_over' => 'Quantity Over',
        'damage' => 'Damage',
        'other' => 'Other',
    ];

    /**
     * Disposition labels
     */
    public const DISPOSITIONS = [
        self::DISPOSITION_PENDING => 'Pending Decision',
        self::DISPOSITION_USE_AS_IS => 'Use As Is',
        self::DISPOSITION_REWORK => 'Rework',
        self::DISPOSITION_SCRAP => 'Scrap',
        self::DISPOSITION_RETURN => 'Return to Supplier',
        self::DISPOSITION_SORT => 'Sort and Use',
        self::DISPOSITION_REJECT => 'Reject',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Related receiving inspection
     */
    public function receivingInspection(): BelongsTo
    {
        return $this->belongsTo(ReceivingInspection::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Supplier relationship
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Reporter relationship
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Reviewer relationship
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Disposition approver relationship
     */
    public function dispositionApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by');
    }

    /**
     * Closer relationship
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get severity label
     */
    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }

    /**
     * Get defect type label
     */
    public function getDefectTypeLabelAttribute(): string
    {
        return self::DEFECT_TYPES[$this->defect_type] ?? $this->defect_type;
    }

    /**
     * Get disposition label
     */
    public function getDispositionLabelAttribute(): string
    {
        return self::DISPOSITIONS[$this->disposition] ?? $this->disposition;
    }

    /**
     * Check if NCR is open
     */
    public function isOpen(): bool
    {
        return !in_array($this->status, [self::STATUS_CLOSED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_PENDING_DISPOSITION,
        ]);
    }

    /**
     * Get days open
     */
    public function getDaysOpenAttribute(): int
    {
        $endDate = $this->closed_at ?? now();
        return (int) $this->reported_at->diffInDays($endDate);
    }

    /**
     * Scope: Open NCRs
     */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope: Closed NCRs
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Scope: By severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: By supplier
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
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
        return $query->whereBetween('reported_at', [$from, $to]);
    }

    /**
     * Scope: Critical and major only
     */
    public function scopeCriticalOrMajor($query)
    {
        return $query->whereIn('severity', [self::SEVERITY_CRITICAL, self::SEVERITY_MAJOR]);
    }
}
