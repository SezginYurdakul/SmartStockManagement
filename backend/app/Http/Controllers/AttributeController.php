<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateVariantsJob;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\VariantGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttributeController extends Controller
{
    protected $variantGenerator;

    public function __construct(VariantGeneratorService $variantGenerator)
    {
        $this->variantGenerator = $variantGenerator;
    }

    /**
     * Display a listing of attributes
     */
    public function index(Request $request)
    {
        $query = Attribute::with(['values' => function ($q) {
            $q->where('is_active', true)->orderBy('order');
        }]);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by variant attributes only
        if ($request->has('variant_only') && $request->variant_only) {
            $query->where('is_variant_attribute', true);
        }

        $attributes = $query->orderBy('order')->get();

        return response()->json($attributes);
    }

    /**
     * Store a newly created attribute
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:attributes|max:255',
            'display_name' => 'required|string|max:255',
            'type' => 'required|in:select,text,number,boolean',
            'order' => 'integer|min:0',
            'is_variant_attribute' => 'boolean',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'description' => 'nullable|string',
            'values' => 'array', // Optional initial values



                                                            'values.*.label' => 'nullable|string',
            'values.*.order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attribute = Attribute::create($request->except('values'));

        // Create initial values if provided
        if ($request->has('values')) {
            foreach ($request->values as $valueData) {
                $attribute->values()->create($valueData);
            }
            $attribute->load('values');
        }

        return response()->json($attribute, 201);
    }

    /**
     * Display the specified attribute
     */
    public function show(Attribute $attribute)
    {
        $attribute->load(['values' => function ($q) {
            $q->orderBy('order');
        }]);

        return response()->json($attribute);
    }

    /**
     * Update the specified attribute
     */
    public function update(Request $request, Attribute $attribute)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|unique:attributes,name,' . $attribute->id . '|max:255',
            'display_name' => 'string|max:255',
            'type' => 'in:select,text,number,boolean',
            'order' => 'integer|min:0',
            'is_variant_attribute' => 'boolean',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attribute->update($request->all());

        return response()->json($attribute);
    }

    /**
     * Remove the specified attribute
     */
    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return response()->json(['message' => 'Attribute deleted successfully']);
    }

    /**
     * Add values to an attribute
     */
    public function addValues(Request $request, Attribute $attribute)
    {
        $validator = Validator::make($request->all(), [
            'values' => 'required|array',
            'values.*.value' => 'required|string',
            'values.*.label' => 'nullable|string',
            'values.*.order' => 'integer|min:0',
            'values.*.is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $createdValues = [];
        foreach ($request->values as $valueData) {
            // Check if value already exists
            $existing = $attribute->values()->where('value', $valueData['value'])->first();
            if (!$existing) {
                $createdValues[] = $attribute->values()->create($valueData);
            }
        }

        return response()->json([
            'message' => count($createdValues) . ' values added successfully',
            'values' => $createdValues
        ], 201);
    }

    /**
     * Update an attribute value
     */
    public function updateValue(Request $request, Attribute $attribute, AttributeValue $value)
    {
        if ($value->attribute_id !== $attribute->id) {
            return response()->json(['error' => 'Value does not belong to this attribute'], 403);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'string',
            'label' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $value->update($request->all());

        return response()->json($value);
    }

    /**
     * Delete an attribute value
     */
    public function destroyValue(Attribute $attribute, AttributeValue $value)
    {
        if ($value->attribute_id !== $attribute->id) {
            return response()->json(['error' => 'Value does not belong to this attribute'], 403);
        }

        $value->delete();
        return response()->json(['message' => 'Value deleted successfully']);
    }

    /**
     * MAGIC ENDPOINT: Generate product variants automatically
     *
     * Example request:
     * POST /api/products/{product}/variants/generate
     * {
     *   "attribute_ids": [1, 2],  // Color and Size attribute IDs
     *   "base_price": 100,
     *   "base_stock": 10,
     *   "price_increments": {
     *     "XL": 10,
     *     "XXL": 20
     *   },
     *   "clear_existing": true
     * }
     *
     * Result: If Color has 5 values and Size has 4 values,
     * this will generate 5 × 4 = 20 variants automatically!
     */
    public function generateVariants(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'attribute_ids' => 'required|array|min:1',
            'attribute_ids.*' => 'exists:attributes,id',
            'base_price' => 'numeric|min:0',
            'base_stock' => 'integer|min:0',
            'price_increments' => 'array',
            'clear_existing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $variants = $this->variantGenerator->generateVariants(
                $product,
                $request->attribute_ids,
                [
                    'base_price' => $request->base_price ?? $product->price,
                    'base_stock' => $request->base_stock ?? 10,
                    'price_increments' => $request->price_increments ?? [],
                    'clear_existing' => $request->clear_existing ?? false,
                ]
            );

            return response()->json([
                'message' => count($variants) . ' variants generated successfully',
                'variants' => $variants
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate variants',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all variants for a product (soft delete)
     */
    public function clearVariants(Product $product)
    {
        $count = $this->variantGenerator->clearVariants($product);

        return response()->json([
            'message' => $count . ' variants deleted successfully',
            'deleted_count' => $count
        ]);
    }

    /**
     * Force clear all variants for a product (permanent delete)
     */
    public function forceClearVariants(Product $product)
    {
        $count = $product->variants()->withTrashed()->forceDelete();

        return response()->json([
            'message' => $count . ' variants permanently deleted',
            'deleted_count' => $count
        ]);
    }

    /**
     * Bulk generate variants for multiple products
     *
     * Example request:
     * POST /api/variants/bulk-generate
     * {
     *   "product_ids": [1, 2, 3, 4, 5],       // Specific products
     *   "category_id": 5,                      // OR all products in category
     *   "attribute_ids": [1, 3],               // Color and Storage
     *   "base_stock": 10,
     *   "clear_existing": false
     * }
     */
    public function bulkGenerateVariants(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required_without:category_id|array',
            'product_ids.*' => 'exists:products,id',
            'category_id' => 'required_without:product_ids|exists:categories,id',
            'attribute_ids' => 'required|array|min:1',
            'attribute_ids.*' => 'exists:attributes,id',
            'base_stock' => 'integer|min:0',
            'clear_existing' => 'boolean',
            'limit' => 'integer|min:1|max:100', // Max products per request (sync mode)
            'offset' => 'integer|min:0', // Skip first N products (sync mode)
            'async' => 'boolean', // Process in background via queue
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get products
        if ($request->has('product_ids')) {
            $products = Product::whereIn('id', $request->product_ids)->get();
            $totalProducts = $products->count();
        } else {
            $totalProducts = Product::where('category_id', $request->category_id)->count();

            // For async mode, get all products; for sync mode, use pagination
            if ($request->boolean('async')) {
                $products = Product::where('category_id', $request->category_id)->get();
            } else {
                $limit = $request->limit ?? 50;
                $offset = $request->offset ?? 0;
                $products = Product::where('category_id', $request->category_id)
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            }
        }

        if ($products->isEmpty()) {
            return response()->json(['error' => 'No products found'], 404);
        }

        // ASYNC MODE: Dispatch jobs to queue
        if ($request->boolean('async')) {
            $jobOptions = [
                'base_stock' => $request->base_stock ?? 10,
                'clear_existing' => $request->clear_existing ?? false,
            ];

            foreach ($products as $product) {
                GenerateVariantsJob::dispatch(
                    $product->id,
                    $request->attribute_ids,
                    array_merge($jobOptions, ['base_price' => $product->price])
                );
            }

            return response()->json([
                'message' => "Variant generation queued for {$products->count()} products",
                'mode' => 'async',
                'jobs_dispatched' => $products->count(),
                'note' => 'Variants will be generated in the background. Check logs for progress.'
            ], 202);
        }

        // SYNC MODE: Process immediately with pagination
        $results = [
            'success' => [],
            'failed' => [],
            'total_variants' => 0,
            'pagination' => isset($offset) ? [
                'total_products_in_category' => $totalProducts,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalProducts,
                'next_offset' => ($offset + $limit) < $totalProducts ? $offset + $limit : null,
            ] : null,
        ];

        foreach ($products as $product) {
            try {
                $variants = $this->variantGenerator->generateVariants(
                    $product,
                    $request->attribute_ids,
                    [
                        'base_price' => $product->price,
                        'base_stock' => $request->base_stock ?? 10,
                        'clear_existing' => $request->clear_existing ?? false,
                    ]
                );

                $results['success'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variants_created' => count($variants)
                ];
                $results['total_variants'] += count($variants);

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => $results['total_variants'] . ' variants generated for ' . count($results['success']) . ' products',
            'mode' => 'sync',
            'results' => $results
        ], 201);
    }
}
