<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Hierarchical tree structure
        if ($request->boolean('tree')) {
            $categories = $query->get()->toTree();
            return response()->json($categories);
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Flat list with pagination - parent bilgisi ile birlikte
        $categories = $query->with('parent') // parent() methodunu kullanarak parent bilgisini yükle
            ->withCount('products')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->load(['parent', 'children']);
        $category->loadCount('products');

        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', Rule::unique('categories')->ignore($category->id)],
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        // Prevent category from being its own parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
            return response()->json([
                'message' => 'Category cannot be its own parent'
            ], 422);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with products. Please move or delete products first.'
            ], 422);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Get category attributes
     */
    public function getAttributes(Category $category)
    {
        $attributes = $category->attributes()->with('values')->get();
        return response()->json($attributes);
    }

    /**
     * Assign attributes to category
     */
    public function assignAttributes(Request $request, Category $category)
    {
        $request->validate([
            'attributes' => 'required|array',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.is_required' => 'boolean',
            'attributes.*.order' => 'integer|min:0',
        ]);

        foreach ($request->attributes as $attr) {
            $category->attributes()->syncWithoutDetaching([
                $attr['attribute_id'] => [
                    'is_required' => $attr['is_required'] ?? false,
                    'order' => $attr['order'] ?? 0
                ]
            ]);
        }

        return response()->json([
            'message' => 'Attributes assigned successfully',
            'attributes' => $category->attributes()->with('values')->get()
        ]);
    }

    /**
     * Update category attribute
     */
    public function updateAttribute(Request $request, Category $category, $attributeId)
    {
        $request->validate([
            'is_required' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $category->attributes()->updateExistingPivot($attributeId,
            array_filter([
                'is_required' => $request->is_required ?? null,
                'order' => $request->order ?? null,
            ], fn($value) => $value !== null)
        );

        return response()->json([
            'message' => 'Attribute updated successfully'
        ]);
    }

    /**
     * Remove attribute from category
     */
    public function removeAttribute(Category $category, $attributeId)
    {
        $category->attributes()->detach($attributeId);

        return response()->json([
            'message' => 'Attribute removed successfully'
        ]);
    }
}
