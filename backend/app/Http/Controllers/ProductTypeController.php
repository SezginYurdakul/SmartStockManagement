<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use App\Services\ProductTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductTypeController extends Controller
{
    protected ProductTypeService $productTypeService;

    public function __construct(ProductTypeService $productTypeService)
    {
        $this->productTypeService = $productTypeService;
    }

    /**
     * Display a listing of product types
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'is_active',
            'can_be_purchased',
            'can_be_sold',
            'can_be_manufactured',
        ]);
        $perPage = $request->get('per_page', 15);

        $productTypes = $this->productTypeService->getProductTypes($filters, $perPage);

        return response()->json($productTypes);
    }

    /**
     * Store a newly created product type
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'can_be_purchased' => 'boolean',
            'can_be_sold' => 'boolean',
            'can_be_manufactured' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $productType = $this->productTypeService->create($validated);

        return response()->json([
            'message' => 'Product type created successfully',
            'data' => $productType,
        ], 201);
    }

    /**
     * Display the specified product type
     */
    public function show(ProductType $productType): JsonResponse
    {
        return response()->json([
            'data' => $this->productTypeService->getProductType($productType),
        ]);
    }

    /**
     * Update the specified product type
     */
    public function update(Request $request, ProductType $productType): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'can_be_purchased' => 'boolean',
            'can_be_sold' => 'boolean',
            'can_be_manufactured' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $productType = $this->productTypeService->update($productType, $validated);

        return response()->json([
            'message' => 'Product type updated successfully',
            'data' => $productType,
        ]);
    }

    /**
     * Remove the specified product type
     */
    public function destroy(ProductType $productType): JsonResponse
    {
        $this->productTypeService->delete($productType);

        return response()->json([
            'message' => 'Product type deleted successfully',
        ]);
    }

    /**
     * Toggle product type active status
     */
    public function toggleActive(ProductType $productType): JsonResponse
    {
        $productType = $this->productTypeService->toggleActive($productType);

        return response()->json([
            'message' => 'Product type status updated successfully',
            'data' => $productType,
        ]);
    }
}
