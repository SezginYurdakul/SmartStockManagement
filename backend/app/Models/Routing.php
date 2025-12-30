<?php

namespace App\Models;

use App\Enums\RoutingStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Routing extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'routing_number',
        'version',
        'name',
        'description',
        'status',
        'is_default',
        'effective_date',
        'expiry_date',
        'notes',
        'meta_data',
        'created_by',
    ];

    protected $casts = [
        'status' => RoutingStatus::class,
        'is_default' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
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
     * Product this routing is for
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Operations in this routing
     */
    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('operation_number');
    }

    /**
     * Work orders using this routing
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Scope: Active routings
     */
    public function scopeActive($query)
    {
        return $query->where('status', RoutingStatus::ACTIVE);
    }

    /**
     * Scope: Default routings
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: For a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Currently effective
     */
    public function scopeEffective($query, ?\DateTimeInterface $date = null)
    {
        $date = $date ?? now();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($q2) use ($date) {
                $q2->whereNull('effective_date')
                   ->orWhere('effective_date', '<=', $date);
            })->where(function ($q2) use ($date) {
                $q2->whereNull('expiry_date')
                   ->orWhere('expiry_date', '>=', $date);
            });
        });
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, RoutingStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Search by number or name
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('routing_number', 'ilike', "%{$term}%")
              ->orWhere('name', 'ilike', "%{$term}%");
        });
    }

    /**
     * Check if routing can be edited
     */
    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if routing can be used for production
     */
    public function canUseForProduction(): bool
    {
        return $this->status->canUseForProduction();
    }

    /**
     * Get operation count
     */
    public function getOperationCountAttribute(): int
    {
        return $this->operations()->count();
    }

    /**
     * Calculate total lead time in minutes
     */
    public function getTotalLeadTimeAttribute(): float
    {
        return $this->operations()->sum(\DB::raw('setup_time + run_time_per_unit + queue_time + move_time'));
    }

    /**
     * Calculate total setup time
     */
    public function getTotalSetupTimeAttribute(): float
    {
        return $this->operations()->sum('setup_time');
    }

    /**
     * Calculate total run time per unit
     */
    public function getTotalRunTimePerUnitAttribute(): float
    {
        return $this->operations()->sum('run_time_per_unit');
    }

    /**
     * Calculate estimated time for a quantity
     */
    public function calculateEstimatedTime(float $quantity): float
    {
        $setupTime = $this->total_setup_time;
        $runTime = $this->total_run_time_per_unit * $quantity;
        $queueMoveTime = $this->operations()->sum(\DB::raw('queue_time + move_time'));

        return $setupTime + $runTime + $queueMoveTime;
    }
}
