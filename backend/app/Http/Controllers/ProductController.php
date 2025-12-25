<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductAttributeResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
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
    public function index(Request $request): AnonymousResourceCollection
    {
        // Search with Elasticsearch
        if ($request->has('search')) {
            $products = Product::search($request->search)
                ->paginate($request->get('per_page', 15));
            return ProductListResource::collection($products);
        }

        $query = Product::with(['categories', 'primaryImage'])
            ->withCount('variants');

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

        return ProductListResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
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
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta_data' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'primary_category_id' => 'nullable|exists:categories,id',
        ]);

        $product = $this->productService->create($validated);
        $product->load(['categories', 'images', 'productType', 'unitOfMeasure']);

        return ProductResource::make($product)
            ->additional(['message' => 'Product created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResource
    {
        $product->load([
            'categories',
            'images',
            'productType',
            'unitOfMeasure',
            'attributes.values',
            'prices.currency',
        ])->loadCount('variants');

        return ProductResource::make($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResource
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
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta_data' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'primary_category_id' => 'nullable|exists:categories,id',
        ]);

        $product = $this->productService->update($product, $validated);
        $product->load(['categories', 'images', 'productType', 'unitOfMeasure']);

        return ProductResource::make($product)
            ->additional(['message' => 'Product updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id): JsonResource
    {
        $product = $this->productService->restore($id);
        $product->load(['categories', 'images', 'productType', 'unitOfMeasure']);

        return ProductResource::make($product)
            ->additional(['message' => 'Product restored successfully']);
    }

    /**
     * Search products using Elasticsearch
     *
     * Accepts: ?search=keyword or ?query=keyword or ?q=keyword
     */
    public function search(Request $request): AnonymousResourceCollection
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

        return ProductListResource::collection($products);
    }

    /**
     * Get product attributes
     */
    public function getAttributes(Product $product): AnonymousResourceCollection
    {
        $attributes = $product->attributes()->with('values')->get();
        return ProductAttributeResource::collection($attributes);
    }

    /**
     * Assign attributes to product
     */
    public function assignAttributes(Request $request, Product $product): AnonymousResourceCollection
    {
        $request->validate([
            'attributes' => 'required|array',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.value' => 'required|string',
        ]);

        foreach ($request->input('attributes') as $attr) {
            $product->attributes()->syncWithoutDetaching([
                $attr['attribute_id'] => ['value' => $attr['value']]
            ]);
        }

        return ProductAttributeResource::collection($product->attributes()->with('values')->get())
            ->additional(['message' => 'Attributes assigned successfully']);
    }

    /**
     * Update product attribute
     */
    public function updateAttribute(Request $request, Product $product, $attributeId): JsonResponse
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
    public function removeAttribute(Product $product, $attributeId): JsonResponse
    {
        $product->attributes()->detach($attributeId);

        return response()->json([
            'message' => 'Attribute removed successfully'
        ]);
    }

    /**
     * Get product variants
     */
    public function getVariants(Product $product): AnonymousResourceCollection
    {
        $variants = $product->variants()->get();
        return ProductVariantResource::collection($variants);
    }

    /**
     * Create a single product variant manually
     */
    public function createVariant(Request $request, Product $product): JsonResponse
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
            return ProductVariantResource::make($existingVariant)
                ->additional(['message' => 'Variant with these attributes already exists'])
                ->response()
                ->setStatusCode(409);
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

        return ProductVariantResource::make($variant)
            ->additional(['message' => 'Variant created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a product variant
     */
    public function updateVariant(Request $request, Product $product, $variantId): JsonResource
    {
        $variant = $product->variants()->findOrFail($variantId);

        $validated = $request->validate([
            'price' => 'nullable|numeric|min:0',
            'stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $variant->update($validated);

        return ProductVariantResource::make($variant)
            ->additional(['message' => 'Variant updated successfully']);
    }

    /**
     * Delete a product variant (soft delete)
     */
    public function deleteVariant(Product $product, $variantId): JsonResponse
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
    public function forceDeleteVariant(Product $product, $variantId): JsonResponse
    {
        $variant = $product->variants()->withTrashed()->findOrFail($variantId);
        $variant->forceDelete();

        return response()->json([
            'message' => 'Variant permanently deleted'
        ]);
    }
}
