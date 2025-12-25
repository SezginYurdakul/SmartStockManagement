<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\WarehouseListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    /**
     * Display a listing of warehouses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'is_active',
            'warehouse_type',
            'city',
            'country',
        ]);
        $perPage = $request->get('per_page', 15);

        $warehouses = $this->warehouseService->getWarehouses($filters, $perPage);

        return WarehouseListResource::collection($warehouses);
    }

    /**
     * Get all active warehouses for dropdowns
     */
    public function list(): JsonResponse
    {
        $warehouses = $this->warehouseService->getActiveWarehouses();

        return response()->json([
            'data' => $warehouses,
        ]);
    }

    /**
     * Store a newly created warehouse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'warehouse_type' => 'required|in:finished_goods,raw_materials,wip,returns',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $warehouse = $this->warehouseService->create($validated);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => WarehouseResource::make($warehouse),
        ], 201);
    }

    /**
     * Display the specified warehouse
     */
    public function show(Warehouse $warehouse): JsonResource
    {
        return WarehouseResource::make(
            $this->warehouseService->getWarehouse($warehouse)
        );
    }

    /**
     * Update the specified warehouse
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'warehouse_type' => 'sometimes|required|in:finished_goods,raw_materials,wip,returns',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $warehouse = $this->warehouseService->update($warehouse, $validated);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => WarehouseResource::make($warehouse),
        ]);
    }

    /**
     * Remove the specified warehouse
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouseService->delete($warehouse);

        return response()->json([
            'message' => 'Warehouse deleted successfully',
        ]);
    }

    /**
     * Toggle warehouse active status
     */
    public function toggleActive(Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseService->toggleActive($warehouse);

        return response()->json([
            'message' => 'Warehouse status updated successfully',
            'data' => WarehouseResource::make($warehouse),
        ]);
    }

    /**
     * Set warehouse as default
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseService->setAsDefault($warehouse);

        return response()->json([
            'message' => 'Default warehouse updated successfully',
            'data' => WarehouseResource::make($warehouse),
        ]);
    }

    /**
     * Get stock summary for a warehouse
     */
    public function stockSummary(Warehouse $warehouse): JsonResponse
    {
        $summary = $this->warehouseService->getStockSummary($warehouse);

        return response()->json([
            'data' => $summary,
        ]);
    }
}
