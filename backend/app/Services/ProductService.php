<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ProductService
{
    /**
     * Apply filters to product query
     */
    public function applyFilters($query, array $filters)
    {
        // Filter by category
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by featured
        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        // Filter by stock status
        if (isset($filters['stock_status'])) {
            $this->applyStockFilter($query, $filters['stock_status']);
        }

        return $query;
    }

    /**
     * Apply stock status filter
     */
    protected function applyStockFilter($query, string $status)
    {
        switch ($status) {
            case 'out_of_stock':
                $query->where('stock', '<=', 0);
                break;
            case 'low_stock':
                $query->whereColumn('stock', '<=', 'low_stock_threshold');
                break;
            case 'in_stock':
                $query->where('stock', '>', 0);
                break;
        }
    }

    /**
     * Apply sorting to query
     */
    public function applySorting($query, string $sortBy = 'created_at', string $sortOrder = 'desc')
    {
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Create a new product
     */
    public function create(array $data): Product
    {
        Log::info('Creating new product', [
            'name' => $data['name'],
            'sku' => $data['sku'],
        ]);

        DB::beginTransaction();

        try {
            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
                Log::debug('Auto-generated slug', ['slug' => $data['slug']]);
            }

            $product = Product::create($data);

            DB::commit();

            Log::info('Product created successfully', [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ]);

            return $product;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create product', [
                'name' => $data['name'] ?? null,
                'sku' => $data['sku'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Failed to create product: {$e->getMessage()}");
        }
    }

    /**
     * Update product
     */
    public function update(Product $product, array $data): Product
    {
        Log::info('Updating product', [
            'product_id' => $product->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            $product->update($data);

            DB::commit();

            Log::info('Product updated successfully', [
                'product_id' => $product->id,
            ]);

            return $product->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to update product: {$e->getMessage()}");
        }
    }

    /**
     * Delete product with all related data
     */
    public function delete(Product $product): bool
    {
        Log::info('Deleting product', [
            'product_id' => $product->id,
            'name' => $product->name,
        ]);

        DB::beginTransaction();

        try {
            $imageCount = $product->images()->count();
            $variantCount = $product->variants()->count();

            // Delete all associated images
            foreach ($product->images as $image) {
                $image->delete();
            }

            // Delete all variants
            $product->variants()->delete();

            // Delete product (soft delete)
            $result = $product->delete();

            DB::commit();

            Log::info('Product deleted successfully', [
                'product_id' => $product->id,
                'images_deleted' => $imageCount,
                'variants_deleted' => $variantCount,
            ]);

            return $result;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete product: {$e->getMessage()}");
        }
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $name, ?int $id = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $id)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    protected function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = Product::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
