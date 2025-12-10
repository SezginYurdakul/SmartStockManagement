<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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

        $query = Product::with(['category', 'primaryImage']);

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
     * Search products using Elasticsearch
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $products = Product::search($request->query)
            ->paginate($request->get('per_page', 15));

        return response()->json($products);
    }
}
