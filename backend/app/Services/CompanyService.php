<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CompanyService
{
    /**
     * Get a single company
     */
    public function getCompany(Company $company): Company
    {
        return $company->loadCount(['users', 'products', 'categories']);
    }

    /**
     * Get paginated companies with optional search
     */
    public function getCompanies(array $filters = [], int $perPage = 15)
    {
        $query = Company::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by country
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new company
     */
    public function create(array $data): Company
    {
        Log::info('Creating new company', ['name' => $data['name']]);

        DB::beginTransaction();

        try {
            $company = Company::create($data);

            DB::commit();

            Log::info('Company created successfully', [
                'company_id' => $company->id,
                'name' => $company->name,
            ]);

            return $company;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create company', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to create company: {$e->getMessage()}");
        }
    }

    /**
     * Update a company
     */
    public function update(Company $company, array $data): Company
    {
        Log::info('Updating company', [
            'company_id' => $company->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $company->update($data);

            DB::commit();

            Log::info('Company updated successfully', [
                'company_id' => $company->id,
            ]);

            return $company->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update company', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to update company: {$e->getMessage()}");
        }
    }

    /**
     * Delete a company (soft delete)
     */
    public function delete(Company $company): bool
    {
        // Check if company has users
        if ($company->users()->count() > 0) {
            throw new BusinessException("Cannot delete company with existing users");
        }

        Log::info('Deleting company', [
            'company_id' => $company->id,
            'name' => $company->name,
        ]);

        return $company->delete();
    }

    /**
     * Restore a soft-deleted company
     */
    public function restore(int $id): Company
    {
        $company = Company::withTrashed()->findOrFail($id);

        if (!$company->trashed()) {
            throw new BusinessException("Company is not deleted");
        }

        $company->restore();

        Log::info('Company restored successfully', [
            'company_id' => $company->id,
        ]);

        return $company;
    }

    /**
     * Toggle company active status
     */
    public function toggleActive(Company $company): Company
    {
        $company->update(['is_active' => !$company->is_active]);

        Log::info('Company active status toggled', [
            'company_id' => $company->id,
            'is_active' => $company->is_active,
        ]);

        return $company;
    }
}
