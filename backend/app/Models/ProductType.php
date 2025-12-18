<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'can_be_purchased',
        'can_be_sold',
        'can_be_manufactured',
        'track_inventory',
        'is_active',
    ];

    protected $casts = [
        'can_be_purchased' => 'boolean',
        'can_be_sold' => 'boolean',
        'can_be_manufactured' => 'boolean',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all products of this type
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope: Get only active product types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get purchasable product types
     */
    public function scopePurchasable($query)
    {
        return $query->where('can_be_purchased', true);
    }

    /**
     * Scope: Get sellable product types
     */
    public function scopeSellable($query)
    {
        return $query->where('can_be_sold', true);
    }

    /**
     * Scope: Get manufacturable product types
     */
    public function scopeManufacturable($query)
    {
        return $query->where('can_be_manufactured', true);
    }
}
