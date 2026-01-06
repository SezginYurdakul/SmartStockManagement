<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CustomerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CustomerGroupService
{
    /**
     * Get paginated customer groups with filters
     */
    public function getCustomerGroups(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerGroup::query()
            ->withCount('customers');

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                  ->orWhere('code', 'ilike', "%{$filters['search']}%");
            });
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get all active customer groups for dropdown
     */
    public function getActiveGroups(): Collection
    {
        return CustomerGroup::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get single customer group
     */
    public function getCustomerGroup(CustomerGroup $customerGroup): CustomerGroup
    {
        return $customerGroup->load(['customers', 'groupPrices.product']);
    }

    /**
     * Create new customer group
     */
    public function create(array $data): CustomerGroup
    {
        Log::info('Creating customer group', [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Generate code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode($data['name'], $companyId);
            }

            $customerGroup = CustomerGroup::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'discount_percentage' => $data['discount_percentage'] ?? 0,
                'payment_terms_days' => $data['payment_terms_days'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            DB::commit();

            Log::info('Customer group created', [
                'customer_group_id' => $customerGroup->id,
                'name' => $customerGroup->name,
            ]);

            return $customerGroup;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create customer group', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update customer group
     */
    public function update(CustomerGroup $customerGroup, array $data): CustomerGroup
    {
        Log::info('Updating customer group', [
            'customer_group_id' => $customerGroup->id,
            'changes' => array_keys($data),
        ]);

        $customerGroup->update([
            'name' => $data['name'] ?? $customerGroup->name,
            'code' => $data['code'] ?? $customerGroup->code,
            'description' => $data['description'] ?? $customerGroup->description,
            'discount_percentage' => $data['discount_percentage'] ?? $customerGroup->discount_percentage,
            'payment_terms_days' => $data['payment_terms_days'] ?? $customerGroup->payment_terms_days,
            'credit_limit' => $data['credit_limit'] ?? $customerGroup->credit_limit,
            'is_active' => $data['is_active'] ?? $customerGroup->is_active,
        ]);

        return $customerGroup->fresh();
    }

    /**
     * Delete customer group
     */
    public function delete(CustomerGroup $customerGroup): bool
    {
        if ($customerGroup->customers()->exists()) {
            throw new BusinessException('Cannot delete customer group with existing customers.');
        }

        Log::info('Deleting customer group', [
            'customer_group_id' => $customerGroup->id,
            'name' => $customerGroup->name,
        ]);

        return $customerGroup->delete();
    }

    /**
     * Generate unique code for customer group
     */
    protected function generateCode(string $name, int $companyId): string
    {
        // Create base code from name
        $baseCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($name, 0, 10)));
        
        if (empty($baseCode)) {
            $baseCode = 'CG';
        }

        // Check if code exists, append number if needed
        $code = $baseCode;
        $counter = 1;
        
        while (CustomerGroup::where('company_id', $companyId)
            ->where('code', $code)
            ->exists()) {
            $code = $baseCode . $counter;
            $counter++;
        }

        return $code;
    }
}
