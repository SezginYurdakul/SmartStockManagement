<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'postal_code',
        'base_currency',
        'supported_currencies',
        'timezone',
        'fiscal_year_start',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'settings' => 'array',
        'fiscal_year_start' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get all users belonging to this company
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all categories belonging to this company
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all products belonging to this company
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all attributes belonging to this company
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class);
    }

    /**
     * Get all product types belonging to this company
     */
    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class);
    }

    /**
     * Get all units of measure belonging to this company
     */
    public function unitsOfMeasure(): HasMany
    {
        return $this->hasMany(UnitOfMeasure::class);
    }

    /**
     * Check if a currency is supported by this company
     */
    public function supportsCurrency(string $currency): bool
    {
        $supported = $this->supported_currencies ?? [$this->base_currency];
        return in_array($currency, $supported);
    }

    /**
     * Get the default currency for this company
     */
    public function getDefaultCurrency(): string
    {
        return $this->base_currency ?? 'USD';
    }
}
