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
     * 
     * ⚠️ SECURITY WARNING: This bypasses company isolation!
     * 
     * This method should ONLY be used by:
     * - Platform/Super administrators (users without company_id or with special permission)
     * - Background jobs that explicitly need cross-company access
     * - System-level operations
     * 
     * ⚠️ DO NOT use this in regular controller/service methods accessible by company admins.
     * Normal company admins should only see their own company's data.
     * 
     * If you need to query a specific company, use scopeForCompany() instead.
     * 
     * @throws \Exception if called by a regular company user (optional - can be enabled for strict mode)
     */
    public function scopeWithoutCompanyScope($query)
    {
        // Strict security check: Only platform admins can bypass company scope
        if (Auth::check()) {
            $user = Auth::user();
            
            // Platform admin: company_id must be null
            // Users with company_id cannot bypass company isolation
            if ($user->company_id !== null) {
                throw new \Exception('Access denied: withoutCompanyScope() can only be used by platform administrators (users with company_id = null). Regular company users cannot bypass company isolation.');
            }
        }
        
        return $query->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * Scope to query a specific company's data
     * 
     * Safer alternative to withoutCompanyScope() when you need to query a specific company.
     * Still bypasses the global scope but explicitly filters by company_id.
     * 
     * Use cases:
     * - Background jobs processing specific companies
     * - Platform admin viewing a specific company's data
     * - System operations that need company-specific queries
     * 
     * @param int $companyId The company ID to query
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId);
    }
}
