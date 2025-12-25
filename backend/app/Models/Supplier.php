<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'supplier_code',
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'fax',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person',
        'contact_email',
        'contact_phone',
        'currency',
        'payment_terms_days',
        'credit_limit',
        'bank_name',
        'bank_account',
        'bank_iban',
        'bank_swift',
        'lead_time_days',
        'minimum_order_amount',
        'shipping_method',
        'rating',
        'notes',
        'meta_data',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'lead_time_days' => 'integer',
        'rating' => 'integer',
        'meta_data' => 'array',
        'is_active' => 'boolean',
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
     * Products this supplier can provide
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->withPivot([
                'supplier_sku',
                'unit_price',
                'currency',
                'minimum_order_qty',
                'lead_time_days',
                'is_preferred',
                'is_active',
            ])
            ->withTimestamps();
    }

    /**
     * Purchase orders from this supplier
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get full address formatted
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope: Active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Search by name, code, or email
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('supplier_code', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('contact_person', 'ilike', "%{$term}%");
        });
    }

    /**
     * Scope: Filter by country
     */
    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope: Filter by rating
     */
    public function scopeByRating($query, int $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Get total purchase order amount
     */
    public function getTotalPurchaseAmountAttribute(): float
    {
        return $this->purchaseOrders()
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->sum('total_amount');
    }

    /**
     * Get pending orders count
     */
    public function getPendingOrdersCountAttribute(): int
    {
        return $this->purchaseOrders()
            ->whereIn('status', ['pending_approval', 'approved', 'sent', 'partially_received'])
            ->count();
    }
}
