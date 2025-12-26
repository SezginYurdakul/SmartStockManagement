<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcceptanceRule extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'category_id',
        'supplier_id',
        'rule_code',
        'name',
        'description',
        'inspection_type',
        'sampling_method',
        'sample_size_percentage',
        'aql_level',
        'aql_value',
        'criteria',
        'is_default',
        'is_active',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'criteria' => 'array',
        'sample_size_percentage' => 'decimal:2',
        'aql_value' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Inspection types
     */
    public const INSPECTION_TYPES = [
        'visual' => 'Visual Inspection',
        'dimensional' => 'Dimensional Inspection',
        'functional' => 'Functional Test',
        'documentation' => 'Documentation Check',
        'sampling' => 'Sample Testing',
    ];

    /**
     * Sampling methods
     */
    public const SAMPLING_METHODS = [
        '100_percent' => '100% Inspection',
        'aql' => 'AQL Sampling',
        'random' => 'Random Sampling',
        'skip_lot' => 'Skip Lot',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Product relationship (optional - specific product rule)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Category relationship (optional - category-wide rule)
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Supplier relationship (optional - supplier-specific rule)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
     * Scope: Active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Default rules
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: Rules for a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Rules for a specific category
     */
    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: Rules for a specific supplier
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: Order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Get human-readable inspection type
     */
    public function getInspectionTypeLabelAttribute(): string
    {
        return self::INSPECTION_TYPES[$this->inspection_type] ?? $this->inspection_type;
    }

    /**
     * Get human-readable sampling method
     */
    public function getSamplingMethodLabelAttribute(): string
    {
        return self::SAMPLING_METHODS[$this->sampling_method] ?? $this->sampling_method;
    }

    /**
     * Calculate sample size for a given quantity
     */
    public function calculateSampleSize(int $quantity): int
    {
        return match ($this->sampling_method) {
            '100_percent' => $quantity,
            'random' => (int) ceil($quantity * ($this->sample_size_percentage / 100)),
            'aql' => $this->calculateAqlSampleSize($quantity),
            'skip_lot' => 0,
            default => $quantity,
        };
    }

    /**
     * Calculate AQL sample size based on quantity and AQL level
     * Simplified AQL table - in production, use full ANSI/ASQ Z1.4 tables
     */
    protected function calculateAqlSampleSize(int $quantity): int
    {
        // Simplified AQL sample sizes (Level II general inspection)
        $aqlTable = [
            ['max' => 8, 'sample' => 2],
            ['max' => 15, 'sample' => 3],
            ['max' => 25, 'sample' => 5],
            ['max' => 50, 'sample' => 8],
            ['max' => 90, 'sample' => 13],
            ['max' => 150, 'sample' => 20],
            ['max' => 280, 'sample' => 32],
            ['max' => 500, 'sample' => 50],
            ['max' => 1200, 'sample' => 80],
            ['max' => 3200, 'sample' => 125],
            ['max' => 10000, 'sample' => 200],
            ['max' => PHP_INT_MAX, 'sample' => 315],
        ];

        foreach ($aqlTable as $row) {
            if ($quantity <= $row['max']) {
                return $row['sample'];
            }
        }

        return 315; // Maximum sample size
    }
}
