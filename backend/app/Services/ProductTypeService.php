<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ProductTypeService
{
    /**
     * Get a single product type with product count
     */
    public function getProductType(ProductType $productType): ProductType
    {
        return $productType->loadCount('products');
    }

    /**
     * Get paginated product types with optional filters
     */
    public function getProductTypes(array $filters = [], int $perPage = 15)
    {
        $query = ProductType::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by capabilities
        if (isset($filters['can_be_purchased'])) {
            $query->where('can_be_purchased', $filters['can_be_purchased']);
        }

        if (isset($filters['can_be_sold'])) {
            $query->where('can_be_sold', $filters['can_be_sold']);
        }

        if (isset($filters['can_be_manufactured'])) {
            $query->where('can_be_manufactured', $filters['can_be_manufactured']);
        }

        return $query->withCount('products')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new product type
     */
    public function create(array $data): ProductType
    {
        Log::info('Creating new product type', ['name' => $data['name']]);

        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueCode($data['name']);
        }

        DB::beginTransaction();

        try {
            $productType = ProductType::create($data);

            DB::commit();

            Log::info('Product type created successfully', [
                'product_type_id' => $productType->id,
                'code' => $productType->code,
            ]);

            return $productType;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create product type', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update a product type
     */
    public function update(ProductType $productType, array $data): ProductType
    {
        Log::info('Updating product type', [
            'product_type_id' => $productType->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $productType->update($data);

            DB::commit();

            Log::info('Product type updated successfully', [
                'product_type_id' => $productType->id,
            ]);

            return $productType->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update product type', [
                'product_type_id' => $productType->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a product type
     */
    public function delete(ProductType $productType): bool
    {
        // Check if product type has products
        if ($productType->products()->count() > 0) {
            throw new BusinessException("Cannot delete product type with existing products. Please reassign or delete the products first.");
        }

        Log::info('Deleting product type', [
            'product_type_id' => $productType->id,
            'code' => $productType->code,
        ]);

        return $productType->delete();
    }

    /**
     * Toggle product type active status
     */
    public function toggleActive(ProductType $productType): ProductType
    {
        $productType->update(['is_active' => !$productType->is_active]);

        Log::info('Product type active status toggled', [
            'product_type_id' => $productType->id,
            'is_active' => $productType->is_active,
        ]);

        return $productType;
    }

    /**
     * Generate unique code from name
     */
    protected function generateUniqueCode(string $name): string
    {
        $code = Str::slug($name, '_');
        $originalCode = $code;
        $counter = 1;

        while (ProductType::where('code', $code)->exists()) {
            $code = $originalCode . '_' . $counter;
            $counter++;
        }

        return $code;
    }
}
