<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id',
        'name',
        'display_name',
        'type',
        'order',
        'is_variant_attribute',
        'is_filterable',
        'is_visible',
        'is_required',
        'description',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_variant_attribute' => 'boolean',
        'is_filterable' => 'boolean',
        'is_visible' => 'boolean',
        'is_required' => 'boolean',
    ];

    /**
     * Get attribute values
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('order');
    }

    /**
     * Get active values only
     */
    public function activeValues(): HasMany
    {
        return $this->values()->where('is_active', true);
    }

    /**
     * Get products that have this attribute
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Get categories that use this attribute
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
            ->withPivot('is_required', 'order')
            ->withTimestamps();
    }

    /**
     * Scope: Get only variant attributes
     */
    public function scopeVariantAttributes($query)
    {
        return $query->where('is_variant_attribute', true);
    }

    /**
     * Scope: Get only filterable attributes
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }
}
