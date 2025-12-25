<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class CategoryService
{
    /**
     * Get categories with optional tree structure
     */
    public function getAll(array $filters = [], bool $tree = false, int $perPage = 15)
    {
        $query = Category::query();

        if ($tree) {
            return $query->get()->toTree();
        }

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query->with('parent')
            ->withCount('products')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new category
     */
    public function create(array $data): Category
    {
        Log::info('Creating new category', ['name' => $data['name']]);

        try {
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }

            $category = Category::create($data);

            Log::info('Category created successfully', [
                'category_id' => $category->id,
                'name' => $category->name,
            ]);

            return $category;

        } catch (Exception $e) {
            Log::error('Failed to create category', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update a category
     */
    public function update(Category $category, array $data): Category
    {
        Log::info('Updating category', [
            'category_id' => $category->id,
            'changes' => array_keys($data),
        ]);

        // Prevent category from being its own parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            throw new BusinessException('Category cannot be its own parent', 400);
        }

        // Prevent circular reference
        if (isset($data['parent_id']) && $this->wouldCreateCircularReference($category, $data['parent_id'])) {
            throw new BusinessException('Cannot set parent: would create circular reference', 400);
        }

        try {
            $category->update($data);

            Log::info('Category updated successfully', ['category_id' => $category->id]);

            return $category->fresh();

        } catch (Exception $e) {
            Log::error('Failed to update category', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a category
     */
    public function delete(Category $category): bool
    {
        Log::info('Deleting category', [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        // Check if category has products
        if ($category->products()->count() > 0) {
            throw new BusinessException('Cannot delete category with products. Please move or delete products first.', 409);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            throw new BusinessException('Cannot delete category with subcategories. Please delete subcategories first.', 409);
        }

        try {
            $result = $category->delete();

            Log::info('Category deleted successfully', ['category_id' => $category->id]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete category', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get category attributes
     */
    public function getAttributes(Category $category)
    {
        return $category->attributes()->with('values')->get();
    }

    /**
     * Assign attributes to category
     */
    public function assignAttributes(Category $category, array $attributes): void
    {
        foreach ($attributes as $attr) {
            $category->attributes()->syncWithoutDetaching([
                $attr['attribute_id'] => [
                    'is_required' => $attr['is_required'] ?? false,
                    'order' => $attr['order'] ?? 0,
                ],
            ]);
        }

        Log::info('Attributes assigned to category', [
            'category_id' => $category->id,
            'attribute_count' => count($attributes),
        ]);
    }

    /**
     * Update category attribute pivot
     */
    public function updateAttribute(Category $category, int $attributeId, array $data): void
    {
        $updateData = array_filter([
            'is_required' => $data['is_required'] ?? null,
            'order' => $data['order'] ?? null,
        ], fn($value) => $value !== null);

        $category->attributes()->updateExistingPivot($attributeId, $updateData);
    }

    /**
     * Remove attribute from category
     */
    public function removeAttribute(Category $category, int $attributeId): void
    {
        $category->attributes()->detach($attributeId);

        Log::info('Attribute removed from category', [
            'category_id' => $category->id,
            'attribute_id' => $attributeId,
        ]);
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
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
        $query = Category::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if setting parent would create circular reference
     */
    protected function wouldCreateCircularReference(Category $category, int $parentId): bool
    {
        $parent = Category::find($parentId);

        while ($parent) {
            if ($parent->id === $category->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }
}
