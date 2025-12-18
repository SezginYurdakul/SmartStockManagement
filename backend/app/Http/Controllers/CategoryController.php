<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttributeResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['parent_id']);
        $tree = $request->boolean('tree');
        $perPage = $request->get('per_page', 15);

        $categories = $this->categoryService->getAll($filters, $tree, $perPage);

        return CategoryResource::collection($categories);
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

        $category = $this->categoryService->create($validated);

        return (new CategoryResource($category))
            ->additional(['message' => 'Category created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->load(['parent', 'children', 'attributes.values']);
        $category->loadCount('products');

        return new CategoryResource($category);
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

        $category = $this->categoryService->update($category, $validated);

        return (new CategoryResource($category))
            ->additional(['message' => 'Category updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $this->categoryService->delete($category);

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Get category attributes
     */
    public function getAttributes(Category $category)
    {
        $attributes = $this->categoryService->getAttributes($category);

        return AttributeResource::collection($attributes);
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

        $this->categoryService->assignAttributes($category, $request->attributes);

        return AttributeResource::collection($this->categoryService->getAttributes($category))
            ->additional(['message' => 'Attributes assigned successfully']);
    }

    /**
     * Update category attribute
     */
    public function updateAttribute(Request $request, Category $category, $attributeId)
    {
        $validated = $request->validate([
            'is_required' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $this->categoryService->updateAttribute($category, $attributeId, $validated);

        return response()->json([
            'message' => 'Attribute updated successfully'
        ]);
    }

    /**
     * Remove attribute from category
     */
    public function removeAttribute(Category $category, $attributeId)
    {
        $this->categoryService->removeAttribute($category, $attributeId);

        return response()->json([
            'message' => 'Attribute removed successfully'
        ]);
    }
}
