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
}
