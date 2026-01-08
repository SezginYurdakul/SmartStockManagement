<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use SoftDeletes, Searchable, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_type_id',
        'uom_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost_price',
        'stock',
        'low_stock_threshold',
        'is_active',
        'is_featured',
        'meta_data',
        'created_by',
        // MRP Planning fields
        'lead_time_days',
        'safety_stock',
        'reorder_point',
        'make_or_buy',
        'low_level_code',
        'minimum_order_qty',
        'order_multiple',
        'maximum_stock',
        // Negative stock policy
        'negative_stock_policy',
        'negative_stock_limit',
        // Reservation policy
        'reservation_policy',
        // Over-delivery tolerance
        'over_delivery_tolerance_percentage',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'meta_data' => 'array',
        // MRP Planning casts
        'lead_time_days' => 'integer',
        'safety_stock' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'low_level_code' => 'integer',
        'minimum_order_qty' => 'decimal:4',
        'order_multiple' => 'decimal:4',
        'maximum_stock' => 'decimal:4',
        'negative_stock_limit' => 'decimal:3',
        'reservation_policy' => 'string',
        'over_delivery_tolerance_percentage' => 'decimal:2',
    ];

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'categories' => $this->categories->pluck('name')->toArray(),
            'category_ids' => $this->categories->pluck('id')->toArray(),
            'primary_category' => $this->primaryCategory?->name,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
        ];
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs()
    {
        return 'products_index';
    }

    /**
     * Determine if the model should be searchable.
     * Only index active products that are not soft deleted.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Boot the model and register event listeners for search indexing.
     */
    protected static function booted(): void
    {
        // Set is_active to false and remove from search index when soft deleted
        static::softDeleted(function (Product $product) {
            $product->updateQuietly(['is_active' => false]);
            $product->unsearchable();
        });

        // Re-activate and add to search index when restored
        static::restored(function (Product $product) {
            $product->updateQuietly(['is_active' => true]);
            $product->searchable();
        });
    }

    /**
     * Get the user who created this product
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the product type
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /**
     * Get the unit of measure
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get all prices for the product
     */
    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Get active prices
     */
    public function activePrices()
    {
        return $this->prices()->active()->validOn();
    }

    /**
     * Get price in a specific currency
     */
    public function getPriceInCurrency(string $currencyCode, string $priceType = 'base', float $quantity = 1): ?float
    {
        $price = $this->prices()
            ->active()
            ->validOn()
            ->inCurrency($currencyCode)
            ->ofType($priceType)
            ->forQuantity($quantity)
            ->first();

        return $price?->unit_price;
    }

    /**
     * Get all categories for the product
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary category for the product
     */
    public function primaryCategory()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->wherePivot('is_primary', true)
            ->limit(1);
    }

    /**
     * Get the primary category (single model, not collection)
     */
    public function getPrimaryCategoryAttribute()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get the variants for the product
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the attributes for the product
     */
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Get the images for the product
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    /**
     * Get the primary image for the product
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }

    /**
     * Check if product is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    // =========================================
    // Manufacturing Module Relationships
    // =========================================

    /**
     * Get all BOMs for this product
     */
    public function boms()
    {
        return $this->hasMany(Bom::class);
    }

    /**
     * Get the default BOM for this product
     */
    public function defaultBom()
    {
        return $this->hasOne(Bom::class)->where('is_default', true)->where('status', 'active');
    }

    /**
     * Get all routings for this product
     */
    public function routings()
    {
        return $this->hasMany(Routing::class);
    }

    /**
     * Get the default routing for this product
     */
    public function defaultRouting()
    {
        return $this->hasOne(Routing::class)->where('is_default', true)->where('status', 'active');
    }

    /**
     * Get all work orders for this product
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Check if product has an active BOM
     */
    public function hasActiveBom(): bool
    {
        return $this->boms()->where('status', 'active')->exists();
    }

    /**
     * Check if product has an active routing
     */
    public function hasActiveRouting(): bool
    {
        return $this->routings()->where('status', 'active')->exists();
    }

    /**
     * Check if product can be manufactured (has both BOM and routing)
     */
    public function canBeManufactured(): bool
    {
        return $this->hasActiveBom() && $this->hasActiveRouting();
    }

    /**
     * Check if product type allows manufacturing (BOM/Routing)
     */
    public function isManufacturable(): bool
    {
        return $this->productType?->can_be_manufactured ?? false;
    }

    /**
     * Check if product can have a BOM attached
     */
    public function canHaveBom(): bool
    {
        return $this->isManufacturable();
    }

    /**
     * Check if product can be used as a component in BOMs
     * (All products can be components, but optionally restrict)
     */
    public function canBeComponent(): bool
    {
        // All inventory-tracked products can be components
        return $this->productType?->track_inventory ?? true;
    }

    /**
     * Get BOMs where this product is used as a component
     * (Where Used / Reverse BOM lookup)
     */
    public function usedInBoms()
    {
        return $this->hasManyThrough(
            Bom::class,
            BomItem::class,
            'component_id', // Foreign key on bom_items
            'id',           // Foreign key on boms
            'id',           // Local key on products
            'bom_id'        // Local key on bom_items
        );
    }

    /**
     * Get BOM items where this product is used as a component
     */
    public function bomItemsAsComponent()
    {
        return $this->hasMany(BomItem::class, 'component_id');
    }

    /**
     * Check if product is used as component in any BOM
     */
    public function isUsedAsComponent(): bool
    {
        return $this->bomItemsAsComponent()->exists();
    }

    // =========================================
    // UOM Conversion Relationships
    // =========================================

    /**
     * Get all product-specific UOM conversions
     */
    public function uomConversions()
    {
        return $this->hasMany(ProductUomConversion::class);
    }

    /**
     * Get active UOM conversions
     */
    public function activeUomConversions()
    {
        return $this->uomConversions()->active();
    }

    /**
     * Convert quantity between units for this product
     *
     * First checks product-specific conversions, then falls back to standard conversions.
     *
     * @param float $quantity Quantity to convert
     * @param UnitOfMeasure $fromUnit Source unit
     * @param UnitOfMeasure $toUnit Target unit
     * @return float|null Converted quantity, null if conversion not possible
     */
    public function convertQuantity(float $quantity, UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): ?float
    {
        // Same unit, no conversion needed
        if ($fromUnit->id === $toUnit->id) {
            return $quantity;
        }

        // Try product-specific conversion first
        $productConversion = $this->uomConversions()
            ->active()
            ->fromUnit($fromUnit->id)
            ->toUnit($toUnit->id)
            ->first();

        if ($productConversion) {
            return $productConversion->convert($quantity);
        }

        // Try reverse product-specific conversion
        $reverseConversion = $this->uomConversions()
            ->active()
            ->fromUnit($toUnit->id)
            ->toUnit($fromUnit->id)
            ->first();

        if ($reverseConversion) {
            return $reverseConversion->reverseConvert($quantity);
        }

        // Fall back to standard conversion
        return $fromUnit->convertTo($quantity, $toUnit);
    }

    /**
     * Get all available units for this product
     * (base unit + all units with conversions)
     */
    public function getAvailableUnits()
    {
        $unitIds = collect([$this->uom_id]);

        // Add units from product-specific conversions
        $conversionUnits = $this->uomConversions()
            ->active()
            ->get()
            ->flatMap(fn($c) => [$c->from_uom_id, $c->to_uom_id]);

        $unitIds = $unitIds->merge($conversionUnits)->unique()->filter();

        return UnitOfMeasure::whereIn('id', $unitIds)->active()->get();
    }

    // =========================================
    // MRP Planning Methods
    // =========================================

    /**
     * Get MRP recommendations for this product
     */
    public function mrpRecommendations()
    {
        return $this->hasMany(MrpRecommendation::class);
    }

    /**
     * Check if product should be manufactured (vs purchased)
     */
    public function shouldManufacture(): bool
    {
        return $this->make_or_buy === 'make';
    }

    /**
     * Check if product should be purchased
     */
    public function shouldPurchase(): bool
    {
        return $this->make_or_buy === 'buy';
    }

    /**
     * Calculate order quantity respecting order multiple and minimum
     */
    public function calculateOrderQuantity(float $netRequirement): float
    {
        // Apply minimum order quantity
        $quantity = max($netRequirement, $this->minimum_order_qty ?? 1);

        // Apply order multiple (lot sizing)
        $multiple = $this->order_multiple ?? 1;
        if ($multiple > 1) {
            $quantity = ceil($quantity / $multiple) * $multiple;
        }

        // Check against maximum stock if set
        if ($this->maximum_stock !== null) {
            $currentStock = $this->getTotalStock();
            $maxOrderQty = $this->maximum_stock - $currentStock;
            if ($maxOrderQty > 0) {
                $quantity = min($quantity, $maxOrderQty);
            }
        }

        return $quantity;
    }

    /**
     * Get total stock across all warehouses
     */
    public function getTotalStock(): float
    {
        return $this->stocks()->sum('quantity_available');
    }

    /**
     * Get stock levels per warehouse
     */
    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Check if product is below reorder point
     */
    public function isBelowReorderPoint(): bool
    {
        $totalStock = $this->getTotalStock();
        return $totalStock < ($this->reorder_point ?? 0);
    }

    /**
     * Check if product is below safety stock
     */
    public function isBelowSafetyStock(): bool
    {
        $totalStock = $this->getTotalStock();
        return $totalStock < ($this->safety_stock ?? 0);
    }

    /**
     * Calculate when order should be placed (considering lead time)
     */
    public function calculateOrderDate(\DateTimeInterface $requiredDate): \DateTimeInterface
    {
        $orderDate = \Carbon\Carbon::parse($requiredDate);
        return $orderDate->subDays($this->lead_time_days ?? 0);
    }

    /**
     * Get negative stock limit based on policy
     */
    public function getNegativeStockLimit(): float
    {
        return match($this->negative_stock_policy ?? 'NEVER') {
            'NEVER' => 0,
            'ALLOWED' => PHP_FLOAT_MAX,
            'LIMITED' => $this->negative_stock_limit ?? 0,
            default => 0,
        };
    }

    /**
     * Check if product can go negative
     */
    public function canGoNegative(): bool
    {
        $policy = $this->negative_stock_policy ?? 'NEVER';
        return $policy !== 'NEVER';
    }
}
