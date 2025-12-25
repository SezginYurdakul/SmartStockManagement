<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use SoftDeletes, BelongsToCompany;

    /**
     * Warehouse types
     */
    public const TYPE_FINISHED_GOODS = 'finished_goods';
    public const TYPE_RAW_MATERIALS = 'raw_materials';
    public const TYPE_WIP = 'wip';
    public const TYPE_RETURNS = 'returns';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'warehouse_type',
        'address',
        'city',
        'country',
        'postal_code',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'is_default',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the user who created this warehouse
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all stock records for this warehouse
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Get all stock movements for this warehouse
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scope to filter active warehouses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by warehouse type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('warehouse_type', $type);
    }

    /**
     * Get the default warehouse for the company
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get full address as string
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get warehouse type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->warehouse_type) {
            self::TYPE_FINISHED_GOODS => 'Finished Goods',
            self::TYPE_RAW_MATERIALS => 'Raw Materials',
            self::TYPE_WIP => 'Work in Progress',
            self::TYPE_RETURNS => 'Returns',
            default => $this->warehouse_type,
        };
    }
}
