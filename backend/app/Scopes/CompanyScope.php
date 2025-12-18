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
        if (Auth::check() && Auth::user()->company_id) {
            $builder->where($model->getTable() . '.company_id', Auth::user()->company_id);
        }
    }
}
