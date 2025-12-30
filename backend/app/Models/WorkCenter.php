<?php

namespace App\Models;

use App\Enums\WorkCenterType;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'work_center_type',
        'cost_per_hour',
        'cost_currency',
        'capacity_per_day',
        'efficiency_percentage',
        'is_active',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'work_center_type' => WorkCenterType::class,
        'cost_per_hour' => 'decimal:4',
        'capacity_per_day' => 'decimal:3',
        'efficiency_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Company relationship
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Routing operations using this work center
     */
    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class);
    }

    /**
     * Work order operations using this work center
     */
    public function workOrderOperations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class);
    }

    /**
     * Scope: Active work centers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, WorkCenterType $type)
    {
        return $query->where('work_center_type', $type);
    }

    /**
     * Scope: Search by name or code
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('code', 'ilike', "%{$term}%");
        });
    }

    /**
     * Get effective capacity (considering efficiency)
     */
    public function getEffectiveCapacityAttribute(): float
    {
        return $this->capacity_per_day * ($this->efficiency_percentage / 100);
    }

    /**
     * Calculate available hours for a date range
     */
    public function calculateAvailableHours(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $days = $startDate->diff($endDate)->days + 1;
        return $days * $this->effective_capacity;
    }

    /**
     * Check if work center can handle the requested hours
     */
    public function hasCapacity(float $requiredHours, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        $availableHours = $this->calculateAvailableHours($startDate, $endDate);

        // Calculate already scheduled hours
        $scheduledHours = $this->workOrderOperations()
            ->whereHas('workOrder', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('planned_start', [$startDate, $endDate])
                  ->orWhereBetween('planned_end', [$startDate, $endDate]);
            })
            ->sum(\DB::raw('(actual_setup_time + actual_run_time) / 60'));

        return ($availableHours - $scheduledHours) >= $requiredHours;
    }
}
