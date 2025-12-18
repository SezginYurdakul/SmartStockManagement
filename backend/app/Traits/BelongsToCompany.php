<?php

namespace App\Traits;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait BelongsToCompany
 *
 * Provides multi-tenant functionality for models that belong to a company.
 * Automatically scopes all queries to the authenticated user's company.
 *
 * Usage:
 * 1. Add `use BelongsToCompany;` to your model
 * 2. Ensure the model's table has a `company_id` column
 * 3. Add 'company_id' to the $fillable array
 */
trait BelongsToCompany
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToCompany(): void
    {
        // Apply global scope to filter by company
        static::addGlobalScope(new CompanyScope());

        // Automatically set company_id when creating new records
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->company_id && empty($model->company_id)) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    /**
     * Get the company that owns this model
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to query without company filter
     * Useful for admin operations that need to see all companies' data
     */
    public function scopeWithoutCompanyScope($query)
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * Scope to query a specific company's data
     * Useful for admin operations or background jobs
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId);
    }
}
