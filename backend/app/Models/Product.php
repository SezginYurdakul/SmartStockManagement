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
     * Alias for backwards compatibility
     * @deprecated Use categories() or primaryCategory instead
     */
    public function category()
    {
        return $this->primaryCategory();
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
}
