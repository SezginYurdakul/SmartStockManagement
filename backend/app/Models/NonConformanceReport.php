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
        $status = $this->status_enum;
        return $status ? $status->fallbackLabel() : ucfirst($this->status ?? 'Unknown');
    }

    /**
     * Get severity label
     */
    public function getSeverityLabelAttribute(): string
    {
        $severity = $this->severity_enum;
        return $severity ? $severity->fallbackLabel() : ucfirst($this->severity ?? 'Unknown');
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
        $disposition = $this->disposition_enum;
        return $disposition ? $disposition->fallbackLabel() : ucfirst($this->disposition ?? 'Unknown');
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
        return $query->whereNotIn('status', [NcrStatus::CLOSED->value, NcrStatus::CANCELLED->value]);
    }

    /**
     * Scope: Closed NCRs
     */
    public function scopeClosed($query)
    {
        return $query->where('status', NcrStatus::CLOSED->value);
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
        return $query->whereIn('severity', [NcrSeverity::CRITICAL->value, NcrSeverity::MAJOR->value]);
    }
}
