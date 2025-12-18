<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateVariantsJob;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\AttributeService;
use App\Services\VariantGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    protected $attributeService;
    protected $variantGenerator;

    public function __construct(AttributeService $attributeService, VariantGeneratorService $variantGenerator)
    {
        $this->attributeService = $attributeService;
        $this->variantGenerator = $variantGenerator;
    }

    /**
     * Display a listing of attributes
     */
    public function index(Request $request)
    {
        $filters = $request->only(['type', 'variant_only']);
        $attributes = $this->attributeService->getAll($filters);

        return response()->json($attributes);
    }

    /**
     * Store a newly created attribute
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:attributes|max:255',
            'display_name' => 'required|string|max:255',
            'type' => 'required|in:select,text,number,boolean',
            'order' => 'integer|min:0',
            'is_variant_attribute' => 'boolean',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'description' => 'nullable|string',
            'values' => 'array',
            'values.*.value' => 'required|string',
            'values.*.label' => 'nullable|string',
            'values.*.order' => 'integer|min:0',
        ]);

        $attribute = $this->attributeService->create($validated);

        return response()->json([
            'message' => 'Attribute created successfully',
            'data' => $attribute
        ], 201);
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
        $validated = $request->validate([
            'name' => ['string', 'max:255', Rule::unique('attributes')->ignore($attribute->id)],
            'display_name' => 'string|max:255',
            'type' => 'in:select,text,number,boolean',
            'order' => 'integer|min:0',
            'is_variant_attribute' => 'boolean',
            'is_filterable' => 'boolean',
            'is_visible' => 'boolean',
            'is_required' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $attribute = $this->attributeService->update($attribute, $validated);

        return response()->json([
            'message' => 'Attribute updated successfully',
            'data' => $attribute
        ]);
    }

    /**
     * Remove the specified attribute
     */
    public function destroy(Attribute $attribute)
    {
        $this->attributeService->delete($attribute);

        return response()->json([
            'message' => 'Attribute deleted successfully'
        ]);
    }

    /**
     * Add values to an attribute
     */
    public function addValues(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'values' => 'required|array',
            'values.*.value' => 'required|string',
            'values.*.label' => 'nullable|string',
            'values.*.order' => 'integer|min:0',
            'values.*.is_active' => 'boolean',
        ]);

        $createdValues = $this->attributeService->addValues($attribute, $validated['values']);

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
        $validated = $request->validate([
            'value' => 'string',
            'label' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $value = $this->attributeService->updateValue($attribute, $value, $validated);

        return response()->json([
            'message' => 'Value updated successfully',
            'data' => $value
        ]);
    }

    /**
     * Delete an attribute value
     */
    public function destroyValue(Attribute $attribute, AttributeValue $value)
    {
        $this->attributeService->deleteValue($attribute, $value);

        return response()->json([
            'message' => 'Value deleted successfully'
        ]);
    }

    /**
     * Generate product variants automatically
     *
     * Request body:
     * - attribute_ids: required array of attribute IDs
     * - selected_value_ids: optional object mapping attribute_id => [value_id1, value_id2, ...]
     *   If not provided for an attribute, all active values are used
     *   Example: {"1": [1, 3, 5], "2": [8, 10]}
     * - base_price: optional starting price (defaults to product price)
     * - base_stock: optional starting stock (defaults to 10)
     * - price_increments: optional object mapping value_id => price_increment
     * - clear_existing: optional boolean to delete existing variants first
     */
    public function generateVariants(Request $request, Product $product)
    {
        $validated = $request->validate([
            'attribute_ids' => 'required|array|min:1',
            'attribute_ids.*' => 'exists:attributes,id',
            'selected_value_ids' => 'array',
            'selected_value_ids.*' => 'array',
            'selected_value_ids.*.*' => 'integer|exists:attribute_values,id',
            'base_price' => 'numeric|min:0',
            'base_stock' => 'integer|min:0',
            'price_increments' => 'array',
            'clear_existing' => 'boolean',
        ]);

        $variants = $this->variantGenerator->generateVariants(
            $product,
            $validated['attribute_ids'],
            [
                'base_price' => $validated['base_price'] ?? $product->price,
                'base_stock' => $validated['base_stock'] ?? 10,
                'price_increments' => $validated['price_increments'] ?? [],
                'clear_existing' => $validated['clear_existing'] ?? false,
                'selected_value_ids' => $validated['selected_value_ids'] ?? [],
            ]
        );

        return response()->json([
            'message' => count($variants) . ' variants generated successfully',
            'variants' => $variants
        ], 201);
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
     * Expand existing variants with a new attribute dimension
     *
     * Takes existing variants and adds a new attribute to create expanded combinations.
     * Price and stock are inherited from original variants by default.
     *
     * Example: 5 size variants + 3 colors = 15 new variants
     *
     * Request body:
     * - expand_attribute_id: required, the attribute ID to expand with
     * - expand_value_ids: required, array of value IDs from the expand attribute
     * - inherit_price_stock: optional boolean (default: true) - inherit from original variants
     * - delete_originals: optional boolean (default: true) - soft-delete original variants
     * - price_increments: optional object mapping value_id => price_increment
     */
    public function expandVariants(Request $request, Product $product)
    {
        $validated = $request->validate([
            'expand_attribute_id' => 'required|integer|exists:attributes,id',
            'expand_value_ids' => 'required|array|min:1',
            'expand_value_ids.*' => 'integer|exists:attribute_values,id',
            'inherit_price_stock' => 'boolean',
            'delete_originals' => 'boolean',
            'price_increments' => 'array',
        ]);

        $variants = $this->variantGenerator->expandVariants(
            $product,
            $validated['expand_attribute_id'],
            $validated['expand_value_ids'],
            [
                'inherit_price_stock' => $validated['inherit_price_stock'] ?? true,
                'delete_originals' => $validated['delete_originals'] ?? true,
                'price_increments' => $validated['price_increments'] ?? [],
            ]
        );

        return response()->json([
            'message' => count($variants) . ' variants created by expansion',
            'variants' => $variants
        ], 201);
    }

    /**
     * Bulk generate variants for multiple products
     */
    public function bulkGenerateVariants(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'required_without:category_id|array',
            'product_ids.*' => 'exists:products,id',
            'category_id' => 'required_without:product_ids|exists:categories,id',
            'attribute_ids' => 'required|array|min:1',
            'attribute_ids.*' => 'exists:attributes,id',
            'base_stock' => 'integer|min:0',
            'clear_existing' => 'boolean',
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
            'async' => 'boolean',
        ]);

        // Get products
        if (isset($validated['product_ids'])) {
            $products = Product::whereIn('id', $validated['product_ids'])->get();
            $totalProducts = $products->count();
        } else {
            $totalProducts = Product::where('category_id', $validated['category_id'])->count();

            if ($request->boolean('async')) {
                $products = Product::where('category_id', $validated['category_id'])->get();
            } else {
                $limit = $validated['limit'] ?? 50;
                $offset = $validated['offset'] ?? 0;
                $products = Product::where('category_id', $validated['category_id'])
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            }
        }

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'No products found',
                'error' => 'not_found'
            ], 404);
        }

        // ASYNC MODE
        if ($request->boolean('async')) {
            $jobOptions = [
                'base_stock' => $validated['base_stock'] ?? 10,
                'clear_existing' => $validated['clear_existing'] ?? false,
            ];

            foreach ($products as $product) {
                GenerateVariantsJob::dispatch(
                    $product->id,
                    $validated['attribute_ids'],
                    array_merge($jobOptions, ['base_price' => $product->price])
                );
            }

            return response()->json([
                'message' => "Variant generation queued for {$products->count()} products",
                'mode' => 'async',
                'jobs_dispatched' => $products->count(),
            ], 202);
        }

        // SYNC MODE
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
                    $validated['attribute_ids'],
                    [
                        'base_price' => $product->price,
                        'base_stock' => $validated['base_stock'] ?? 10,
                        'clear_existing' => $validated['clear_existing'] ?? false,
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
