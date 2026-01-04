<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Services\ProductUomConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductUomConversionController extends Controller
{
    protected ProductUomConversionService $conversionService;

    public function __construct(ProductUomConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    /**
     * Get all conversions for a product
     */
    public function index(Product $product): JsonResponse
    {
        $data = $this->conversionService->getConversionsForProduct($product);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Store a new conversion for a product
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'from_uom_id' => 'required|exists:units_of_measure,id',
            'to_uom_id' => 'required|exists:units_of_measure,id|different:from_uom_id',
            'conversion_factor' => 'required|numeric|gt:0',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Check if conversion already exists
        $exists = $product->uomConversions()
            ->where('from_uom_id', $validated['from_uom_id'])
            ->where('to_uom_id', $validated['to_uom_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A conversion between these units already exists for this product.',
            ], 422);
        }

        $conversion = $this->conversionService->create($product, $validated);

        return response()->json([
            'message' => 'Product conversion created successfully',
            'data' => $conversion->load(['fromUom', 'toUom']),
        ], 201);
    }

    /**
     * Show a specific conversion
     */
    public function show(Product $product, ProductUomConversion $conversion): JsonResponse
    {
        // Ensure conversion belongs to product
        if ($conversion->product_id !== $product->id) {
            return response()->json([
                'message' => 'Conversion not found for this product.',
            ], 404);
        }

        return response()->json([
            'data' => $conversion->load(['fromUom', 'toUom', 'product']),
        ]);
    }

    /**
     * Update a conversion
     */
    public function update(Request $request, Product $product, ProductUomConversion $conversion): JsonResponse
    {
        // Ensure conversion belongs to product
        if ($conversion->product_id !== $product->id) {
            return response()->json([
                'message' => 'Conversion not found for this product.',
            ], 404);
        }

        $validated = $request->validate([
            'from_uom_id' => 'sometimes|required|exists:units_of_measure,id',
            'to_uom_id' => 'sometimes|required|exists:units_of_measure,id',
            'conversion_factor' => 'sometimes|required|numeric|gt:0',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate if changing units
        if (isset($validated['from_uom_id']) || isset($validated['to_uom_id'])) {
            $fromId = $validated['from_uom_id'] ?? $conversion->from_uom_id;
            $toId = $validated['to_uom_id'] ?? $conversion->to_uom_id;

            if ($fromId === $toId) {
                return response()->json([
                    'message' => 'From and To units must be different.',
                ], 422);
            }

            $exists = $product->uomConversions()
                ->where('id', '!=', $conversion->id)
                ->where('from_uom_id', $fromId)
                ->where('to_uom_id', $toId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'A conversion between these units already exists for this product.',
                ], 422);
            }
        }

        $conversion = $this->conversionService->update($conversion, $validated);

        return response()->json([
            'message' => 'Product conversion updated successfully',
            'data' => $conversion,
        ]);
    }

    /**
     * Delete a conversion
     */
    public function destroy(Product $product, ProductUomConversion $conversion): JsonResponse
    {
        // Ensure conversion belongs to product
        if ($conversion->product_id !== $product->id) {
            return response()->json([
                'message' => 'Conversion not found for this product.',
            ], 404);
        }

        $this->conversionService->delete($conversion);

        return response()->json([
            'message' => 'Product conversion deleted successfully',
        ]);
    }

    /**
     * Toggle conversion active status
     */
    public function toggleActive(Product $product, ProductUomConversion $conversion): JsonResponse
    {
        // Ensure conversion belongs to product
        if ($conversion->product_id !== $product->id) {
            return response()->json([
                'message' => 'Conversion not found for this product.',
            ], 404);
        }

        $conversion = $this->conversionService->toggleActive($conversion);

        return response()->json([
            'message' => 'Product conversion status updated successfully',
            'data' => $conversion,
        ]);
    }

    /**
     * Convert quantity using product-specific or standard conversion
     */
    public function convert(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|gt:0',
            'from_uom_id' => 'required|exists:units_of_measure,id',
            'to_uom_id' => 'required|exists:units_of_measure,id',
        ]);

        $result = $this->conversionService->convert(
            $product,
            $validated['quantity'],
            $validated['from_uom_id'],
            $validated['to_uom_id']
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Bulk create conversions
     */
    public function bulkStore(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'conversions' => 'required|array|min:1',
            'conversions.*.from_uom_id' => 'required|exists:units_of_measure,id',
            'conversions.*.to_uom_id' => 'required|exists:units_of_measure,id',
            'conversions.*.conversion_factor' => 'required|numeric|gt:0',
            'conversions.*.is_default' => 'boolean',
            'conversions.*.is_active' => 'boolean',
        ]);

        $created = $this->conversionService->bulkCreate($product, $validated['conversions']);

        return response()->json([
            'message' => count($created) . ' conversions created successfully',
            'data' => $created,
        ], 201);
    }

    /**
     * Copy conversions from another product
     */
    public function copyFrom(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'source_product_id' => 'required|exists:products,id',
        ]);

        $sourceProduct = Product::findOrFail($validated['source_product_id']);
        $copied = $this->conversionService->copyFromProduct($sourceProduct, $product);

        return response()->json([
            'message' => count($copied) . ' conversions copied successfully',
            'data' => $copied,
        ], 201);
    }
}
