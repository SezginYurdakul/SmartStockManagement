<?php

namespace App\Models;

use App\Enums\BomType;
use App\Enums\BomStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'bom_number',
        'version',
        'name',
        'description',
        'bom_type',
        'status',
        'quantity',
        'uom_id',
        'is_default',
        'effective_date',
        'expiry_date',
        'notes',
        'meta_data',
        'created_by',
    ];

    protected $casts = [
        'bom_type' => BomType::class,
        'status' => BomStatus::class,
        'quantity' => 'decimal:4',
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
     * Product being manufactured
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Unit of measure
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * BOM items (components)
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('line_number');
    }

    /**
     * Work orders using this BOM
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Scope: Active BOMs
     */
    public function scopeActive($query)
    {
        return $query->where('status', BomStatus::ACTIVE);
    }

    /**
     * Scope: Default BOMs
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
     * Scope: Currently effective BOMs
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
    public function scopeByStatus($query, BomStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, BomType $type)
    {
        return $query->where('bom_type', $type);
    }

    /**
     * Scope: Search by number or name
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('bom_number', 'ilike', "%{$term}%")
              ->orWhere('name', 'ilike', "%{$term}%");
        });
    }

    /**
     * Check if BOM can be edited
     */
    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    /**
     * Check if BOM can be used for production
     */
    public function canUseForProduction(): bool
    {
        return $this->status->canUseForProduction();
    }

    /**
     * Get total component count
     */
    public function getComponentCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Check if BOM has any phantom items
     */
    public function hasPhantomItems(): bool
    {
        return $this->items()->where('is_phantom', true)->exists();
    }
}
