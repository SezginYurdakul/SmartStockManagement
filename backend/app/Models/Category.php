<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'parent_id',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the user who created this category
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all products in this category
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get products where this is the primary category
     */
    public function primaryProducts()
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    /**
     * Get the attributes for this category
     */
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')
            ->withPivot('is_required', 'order')
            ->withTimestamps()
            ->orderBy('order');
    }

    /**
     * Get parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get products including subcategories
     */
    public function allProducts()
    {
        $categoryIds = $this->children()->pluck('id')->push($this->id);
        return Product::whereHas('categories', function ($query) use ($categoryIds) {
            $query->whereIn('categories.id', $categoryIds);
        });
    }
}
