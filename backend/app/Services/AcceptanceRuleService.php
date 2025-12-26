<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AcceptanceRule;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AcceptanceRuleService
{
    /**
     * Get paginated acceptance rules with filters
     */
    public function getAcceptanceRules(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AcceptanceRule::query()
            ->with(['product', 'category', 'supplier', 'creator']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('rule_code', 'ilike', "%{$filters['search']}%")
                  ->orWhere('name', 'ilike', "%{$filters['search']}%");
            });
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Inspection type filter
        if (!empty($filters['inspection_type'])) {
            $query->where('inspection_type', $filters['inspection_type']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->forCategory($filters['category_id']);
        }

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $query->forSupplier($filters['supplier_id']);
        }

        return $query->byPriority()->paginate($perPage);
    }

    /**
     * Get list for dropdowns
     */
    public function getList(): Collection
    {
        return AcceptanceRule::query()
            ->active()
            ->select(['id', 'rule_code', 'name', 'inspection_type'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get single rule
     */
    public function getAcceptanceRule(AcceptanceRule $rule): AcceptanceRule
    {
        return $rule->load(['product', 'category', 'supplier', 'creator', 'updater']);
    }

    /**
     * Create new acceptance rule
     */
    public function create(array $data): AcceptanceRule
    {
        Log::info('Creating new acceptance rule', [
            'rule_code' => $data['rule_code'] ?? null,
            'name' => $data['name'],
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Generate rule code if not provided
            if (empty($data['rule_code'])) {
                $data['rule_code'] = $this->generateRuleCode();
            }

            // If setting as default, clear other defaults
            if (!empty($data['is_default'])) {
                AcceptanceRule::where('company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $rule = AcceptanceRule::create([
                'company_id' => $companyId,
                'product_id' => $data['product_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'rule_code' => $data['rule_code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'inspection_type' => $data['inspection_type'] ?? 'visual',
                'sampling_method' => $data['sampling_method'] ?? '100_percent',
                'sample_size_percentage' => $data['sample_size_percentage'] ?? null,
                'aql_level' => $data['aql_level'] ?? null,
                'aql_value' => $data['aql_value'] ?? null,
                'criteria' => $data['criteria'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'priority' => $data['priority'] ?? 0,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Acceptance rule created successfully', [
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
            ]);

            return $rule->fresh(['product', 'category', 'supplier']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create acceptance rule', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update acceptance rule
     */
    public function update(AcceptanceRule $rule, array $data): AcceptanceRule
    {
        Log::info('Updating acceptance rule', [
            'rule_id' => $rule->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            // If setting as default, clear other defaults
            if (!empty($data['is_default']) && !$rule->is_default) {
                AcceptanceRule::where('company_id', $rule->company_id)
                    ->where('id', '!=', $rule->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $rule->update([
                'product_id' => $data['product_id'] ?? $rule->product_id,
                'category_id' => $data['category_id'] ?? $rule->category_id,
                'supplier_id' => $data['supplier_id'] ?? $rule->supplier_id,
                'name' => $data['name'] ?? $rule->name,
                'description' => $data['description'] ?? $rule->description,
                'inspection_type' => $data['inspection_type'] ?? $rule->inspection_type,
                'sampling_method' => $data['sampling_method'] ?? $rule->sampling_method,
                'sample_size_percentage' => $data['sample_size_percentage'] ?? $rule->sample_size_percentage,
                'aql_level' => $data['aql_level'] ?? $rule->aql_level,
                'aql_value' => $data['aql_value'] ?? $rule->aql_value,
                'criteria' => $data['criteria'] ?? $rule->criteria,
                'is_default' => $data['is_default'] ?? $rule->is_default,
                'is_active' => $data['is_active'] ?? $rule->is_active,
                'priority' => $data['priority'] ?? $rule->priority,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Acceptance rule updated successfully', [
                'rule_id' => $rule->id,
            ]);

            return $rule->fresh(['product', 'category', 'supplier']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update acceptance rule', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete acceptance rule
     */
    public function delete(AcceptanceRule $rule): bool
    {
        Log::info('Deleting acceptance rule', [
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
        ]);

        return $rule->delete();
    }

    /**
     * Find applicable rule for a product/supplier combination
     * Priority order: Product+Supplier > Product > Category+Supplier > Category > Supplier > Default
     */
    public function findApplicableRule(int $productId, ?int $supplierId = null): ?AcceptanceRule
    {
        $companyId = Auth::user()->company_id;
        $product = Product::with('categories')->find($productId);

        if (!$product) {
            return $this->getDefaultRule();
        }

        $categoryIds = $product->categories->pluck('id')->toArray();

        // Try to find most specific rule
        $query = AcceptanceRule::where('company_id', $companyId)
            ->active()
            ->byPriority();

        // 1. Product + Supplier specific
        if ($supplierId) {
            $rule = (clone $query)
                ->where('product_id', $productId)
                ->where('supplier_id', $supplierId)
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // 2. Product specific
        $rule = (clone $query)
            ->where('product_id', $productId)
            ->whereNull('supplier_id')
            ->first();

        if ($rule) {
            return $rule;
        }

        // 3. Category + Supplier specific
        if ($supplierId && !empty($categoryIds)) {
            $rule = (clone $query)
                ->whereIn('category_id', $categoryIds)
                ->where('supplier_id', $supplierId)
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // 4. Category specific
        if (!empty($categoryIds)) {
            $rule = (clone $query)
                ->whereIn('category_id', $categoryIds)
                ->whereNull('supplier_id')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // 5. Supplier specific
        if ($supplierId) {
            $rule = (clone $query)
                ->whereNull('product_id')
                ->whereNull('category_id')
                ->where('supplier_id', $supplierId)
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // 6. Default rule
        return $this->getDefaultRule();
    }

    /**
     * Get default rule for company
     */
    public function getDefaultRule(): ?AcceptanceRule
    {
        $companyId = Auth::user()->company_id;

        return AcceptanceRule::where('company_id', $companyId)
            ->active()
            ->default()
            ->first();
    }

    /**
     * Generate next rule code
     */
    public function generateRuleCode(): string
    {
        $companyId = Auth::user()->company_id;
        $prefix = "AR-";

        $lastRule = AcceptanceRule::withTrashed()
            ->where('company_id', $companyId)
            ->where('rule_code', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(rule_code FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastRule && preg_match('/(\d+)$/', $lastRule->rule_code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get inspection types for dropdown
     */
    public function getInspectionTypes(): array
    {
        return AcceptanceRule::INSPECTION_TYPES;
    }

    /**
     * Get sampling methods for dropdown
     */
    public function getSamplingMethods(): array
    {
        return AcceptanceRule::SAMPLING_METHODS;
    }
}
