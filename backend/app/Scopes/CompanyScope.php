<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Automatically filters all queries by the authenticated user's company_id.
     * This ensures data isolation between companies (multi-tenancy).
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply if user is authenticated and has a company
        // Platform admins (company_id null) can see all companies
        if (Auth::check()) {
            $user = Auth::user();
            
            // Skip scope for platform admins (company_id null)
            if ($user->company_id === null) {
                return; // Platform admin can see all companies
            }
            
            // Apply company scope for regular users
            if ($user->company_id) {
                $builder->where($model->getTable() . '.company_id', $user->company_id);
            }
        }
    }
}
