<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Search with Elasticsearch
        if ($request->has('search')) {
            $products = Product::search($request->search)
                ->paginate($request->get('per_page', 15));
            return response()->json($products);
        }

        $query = Product::with(['category', 'primaryImage'])
            ->withCount('images');

        // Apply filters
        $filters = $request->only(['category_id', 'is_active', 'is_featured', 'stock_status']);
        $query = $this->productService->applyFilters($query, $filters);

        // Apply sorting
        $query = $this->productService->applySorting(
            $query,
            $request->get('sort_by', 'created_at'),
            $request->get('sort_order', 'desc')
        );

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'sku' => 'required|string|unique:products,sku',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta_data' => 'nullable|array',
        ]);

        $product = $this->productService->create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'images'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load([
            'category',
            'images',
            'variants' => function ($query) {
                $query->where('is_active', true);
            }
        ]);

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', Rule::unique('products')->ignore($product->id)],
            'sku' => ['sometimes', 'required', 'string', Rule::unique('products')->ignore($product->id)],
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta_data' => 'nullable|array',
        ]);

        $product = $this->productService->update($product, $validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'images'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id)
    {
        $product = $this->productService->restore($id);

        return response()->json([
            'message' => 'Product restored successfully',
            'data' => $product->load(['category', 'images'])
        ]);
    }

    /**
     * Search products using Elasticsearch
     *
     * Accepts: ?search=keyword or ?query=keyword or ?q=keyword
     */
    public function search(Request $request)
    {
        // Accept multiple parameter names for flexibility
        $searchTerm = $request->input('search')
            ?? $request->input('query')
            ?? $request->input('q');

        if (!$searchTerm || strlen($searchTerm) < 2) {
            throw new BusinessException('The search term is required and must be at least 2 characters.', 422);
        }

        $products = Product::search($searchTerm)
            ->paginate($request->get('per_page', 15));

        return response()->json($products);
    }

    /**
     * Get product attributes
     */
    public function getAttributes(Product $product)
    {
        $attributes = $product->attributes()->with('values')->get();
        return response()->json($attributes);
    }

    /**
     * Assign attributes to product
     */
    public function assignAttributes(Request $request, Product $product)
    {
        $request->validate([
            'attributes' => 'required|array',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.value' => 'required|string',
        ]);

        foreach ($request->attributes as $attr) {
            $product->attributes()->syncWithoutDetaching([
                $attr['attribute_id'] => ['value' => $attr['value']]
            ]);
        }

        return response()->json([
            'message' => 'Attributes assigned successfully',
            'attributes' => $product->attributes()->with('values')->get()
        ]);
    }

    /**
     * Update product attribute
     */
    public function updateAttribute(Request $request, Product $product, $attributeId)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $product->attributes()->updateExistingPivot($attributeId, [
            'value' => $request->value
        ]);

        return response()->json([
            'message' => 'Attribute updated successfully'
        ]);
    }

    /**
     * Remove attribute from product
     */
    public function removeAttribute(Product $product, $attributeId)
    {
        $product->attributes()->detach($attributeId);

        return response()->json([
            'message' => 'Attribute removed successfully'
        ]);
    }

    /**
     * Get product variants
     */
    public function getVariants(Product $product)
    {
        $variants = $product->variants()->get();
        return response()->json($variants);
    }

    /**
     * Create a single product variant manually
     */
    public function createVariant(Request $request, Product $product)
    {
        $validated = $request->validate([
            'attributes' => 'required|array',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate variant
        $existingVariant = $product->variants()
            ->get()
            ->first(function ($variant) use ($validated) {
                $existing = $variant->attributes ?? [];
                $new = $validated['attributes'];
                ksort($existing);
                ksort($new);
                return $existing == $new;
            });

        if ($existingVariant) {
            return response()->json([
                'message' => 'Variant with these attributes already exists',
                'variant' => $existingVariant
            ], 409);
        }

        // Generate variant name and SKU
        $variantName = implode(' - ', array_values($validated['attributes']));
        $baseSku = $product->sku;
        $suffix = strtoupper(implode('-', array_map('Illuminate\Support\Str::slug', array_values($validated['attributes']))));
        $sku = "{$baseSku}-{$suffix}";

        // Ensure SKU uniqueness
        $counter = 1;
        $originalSku = $sku;
        while (\App\Models\ProductVariant::where('sku', $sku)->exists()) {
            $sku = "{$originalSku}-{$counter}";
            $counter++;
        }

        $variant = $product->variants()->create([
            'name' => $variantName,
            'sku' => $sku,
            'price' => $validated['price'] ?? null,
            'stock' => $validated['stock'] ?? 0,
            'attributes' => $validated['attributes'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Variant created successfully',
            'variant' => $variant
        ], 201);
    }

    /**
     * Update a product variant
     */
    public function updateVariant(Request $request, Product $product, $variantId)
    {
        $variant = $product->variants()->findOrFail($variantId);

        $validated = $request->validate([
            'price' => 'nullable|numeric|min:0',
            'stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $variant->update($validated);

        return response()->json([
            'message' => 'Variant updated successfully',
            'variant' => $variant
        ]);
    }

    /**
     * Delete a product variant (soft delete)
     */
    public function deleteVariant(Product $product, $variantId)
    {
        $variant = $product->variants()->findOrFail($variantId);
        $variant->delete();

        return response()->json([
            'message' => 'Variant deleted successfully'
        ]);
    }

    /**
     * Force delete a product variant (permanent)
     */
    public function forceDeleteVariant(Product $product, $variantId)
    {
        $variant = $product->variants()->withTrashed()->findOrFail($variantId);
        $variant->forceDelete();

        return response()->json([
            'message' => 'Variant permanently deleted'
        ]);
    }
}
