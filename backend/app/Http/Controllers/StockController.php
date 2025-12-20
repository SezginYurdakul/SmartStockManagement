<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use App\Http\Resources\StockResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Display a listing of stock records
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'product_id',
            'warehouse_id',
            'status',
            'lot_number',
            'low_stock',
            'expiring_days',
            'expired',
        ]);
        $perPage = $request->get('per_page', 15);

        $stocks = $this->stockService->getStocks($filters, $perPage);

        return StockResource::collection($stocks);
    }

    /**
     * Get stock for a specific product
     */
    public function productStock(Request $request, int $productId): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $stock = $this->stockService->getProductStock($productId, $warehouseId);

        return response()->json([
            'data' => $stock,
        ]);
    }

    /**
     * Get stock for a specific warehouse
     */
    public function warehouseStock(int $warehouseId): JsonResponse
    {
        $stock = $this->stockService->getWarehouseStock($warehouseId);

        return response()->json([
            'data' => $stock,
        ]);
    }

    /**
     * Receive stock (inbound)
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'unit_cost' => 'nullable|numeric|min:0',
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'transaction_type' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $stock = $this->stockService->receiveStock($validated);

        return response()->json([
            'message' => 'Stock received successfully',
            'data' => new StockResource($stock),
        ], 201);
    }

    /**
     * Issue stock (outbound)
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'transaction_type' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $stock = $this->stockService->issueStock($validated);

        return response()->json([
            'message' => 'Stock issued successfully',
            'data' => new StockResource($stock),
        ]);
    }

    /**
     * Transfer stock between warehouses
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|numeric|min:0.001',
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $result = $this->stockService->transferStock($validated);

        return response()->json([
            'message' => 'Stock transferred successfully',
            'data' => [
                'source' => new StockResource($result['source']),
                'destination' => new StockResource($result['destination']),
            ],
        ]);
    }

    /**
     * Adjust stock (inventory adjustment)
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'new_quantity' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $stock = $this->stockService->adjustStock($validated);

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'data' => new StockResource($stock),
        ]);
    }

    /**
     * Reserve stock
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'lot_number' => 'nullable|string|max:100',
        ]);

        $stock = $this->stockService->reserveStock(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['quantity'],
            $validated['lot_number'] ?? null
        );

        return response()->json([
            'message' => 'Stock reserved successfully',
            'data' => new StockResource($stock),
        ]);
    }

    /**
     * Release reserved stock
     */
    public function releaseReservation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'lot_number' => 'nullable|string|max:100',
        ]);

        $stock = $this->stockService->releaseReservation(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['quantity'],
            $validated['lot_number'] ?? null
        );

        return response()->json([
            'message' => 'Stock reservation released successfully',
            'data' => new StockResource($stock),
        ]);
    }

    /**
     * Get low stock products
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->get('per_page', 15);
        $stocks = $this->stockService->getLowStockProducts($perPage);

        return StockResource::collection($stocks);
    }

    /**
     * Get expiring stock
     */
    public function expiring(Request $request): AnonymousResourceCollection
    {
        $days = $request->get('days', 30);
        $perPage = $request->get('per_page', 15);

        $stocks = $this->stockService->getExpiringStock($days, $perPage);

        return StockResource::collection($stocks);
    }
}
