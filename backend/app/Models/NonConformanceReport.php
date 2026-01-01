<?php

namespace App\Models;

use App\Enums\DefectType;
use App\Enums\NcrDisposition;
use App\Enums\NcrSeverity;
use App\Enums\NcrStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonConformanceReport extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    // Legacy constants - kept for backward compatibility, prefer using Enums
    // @deprecated Use NcrStatus enum instead
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_PENDING_DISPOSITION = 'pending_disposition';
    public const STATUS_DISPOSITION_APPROVED = 'disposition_approved';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // @deprecated Use NcrSeverity enum instead
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MAJOR = 'major';
    public const SEVERITY_CRITICAL = 'critical';

    // Source type constants (no enum needed - simple category)
    public const SOURCE_RECEIVING = 'receiving';
    public const SOURCE_PRODUCTION = 'production';
    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_CUSTOMER = 'customer';

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    // @deprecated Use NcrDisposition enum instead
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
     * Status labels for UI
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
     * Severity labels for UI
     */
    public const SEVERITIES = [
        self::SEVERITY_MINOR => 'Minor',
        self::SEVERITY_MAJOR => 'Major',
        self::SEVERITY_CRITICAL => 'Critical',
    ];

    /**
     * Defect type labels for UI
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
     * Disposition labels for UI
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
     * Source type labels for UI
     */
    public const SOURCES = [
        self::SOURCE_RECEIVING => 'Receiving',
        self::SOURCE_PRODUCTION => 'Production',
        self::SOURCE_INTERNAL => 'Internal',
        self::SOURCE_CUSTOMER => 'Customer',
    ];

    /**
     * Priority labels for UI
     */
    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    /**
     * Get status as Enum
     */
    public function getStatusEnumAttribute(): ?NcrStatus
    {
        return $this->status ? NcrStatus::tryFrom($this->status) : null;
    }

    /**
     * Get severity as Enum
     */
    public function getSeverityEnumAttribute(): ?NcrSeverity
    {
        return $this->severity ? NcrSeverity::tryFrom($this->severity) : null;
    }

    /**
     * Get disposition as Enum
     */
    public function getDispositionEnumAttribute(): ?NcrDisposition
    {
        return $this->disposition ? NcrDisposition::tryFrom($this->disposition) : null;
    }

    /**
     * Get defect type as Enum
     */
    public function getDefectTypeEnumAttribute(): ?DefectType
    {
        return $this->defect_type ? DefectType::tryFrom($this->defect_type) : null;
    }

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
        return self::STATUSES[$this->status] ?? ucfirst($this->status ?? 'Unknown');
    }

    /**
     * Get severity label
     */
    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? ucfirst($this->severity ?? 'Unknown');
    }

    /**
     * Get defect type label
     */
    public function getDefectTypeLabelAttribute(): string
    {
        return self::DEFECT_TYPES[$this->defect_type] ?? ucfirst($this->defect_type ?? 'Unknown');
    }

    /**
     * Get disposition label
     */
    public function getDispositionLabelAttribute(): string
    {
        return self::DISPOSITIONS[$this->disposition] ?? ucfirst($this->disposition ?? 'Unknown');
    }

    /**
     * Check if NCR is open (using Enum for type-safe logic)
     */
    public function isOpen(): bool
    {
        $status = $this->status_enum;
        return $status ? $status->isActive() : true;
    }

    /**
     * Check if can be edited (using Enum)
     */
    public function canBeEdited(): bool
    {
        $status = $this->status_enum;
        return $status ? $status->canEdit() : false;
    }

    /**
     * Check if requires quarantine based on severity (using Enum)
     */
    public function requiresQuarantine(): bool
    {
        $severity = $this->severity_enum;
        return $severity ? $severity->requiresQuarantine() : false;
    }

    /**
     * Get response time in hours based on severity (using Enum)
     */
    public function getResponseTimeHours(): int
    {
        $severity = $this->severity_enum;
        return $severity ? $severity->responseTimeHours() : 72;
    }

    /**
     * Check if should notify supplier (using Enum)
     */
    public function shouldNotifySupplier(): bool
    {
        $disposition = $this->disposition_enum;
        return $disposition ? $disposition->notifySupplier() : false;
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
