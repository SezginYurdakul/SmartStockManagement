<?php

namespace App\Http\Controllers;

use App\Services\StockMovementService;
use App\Http\Resources\StockMovementResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Carbon\Carbon;

class StockMovementController extends Controller
{
    public function __construct(
        protected StockMovementService $movementService
    ) {}

    /**
     * Display a listing of stock movements
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'product_id',
            'warehouse_id',
            'movement_type',
            'transaction_type',
            'reference_number',
            'start_date',
            'end_date',
            'direction',
        ]);
        $perPage = $request->get('per_page', 15);

        $movements = $this->movementService->getMovements($filters, $perPage);

        return StockMovementResource::collection($movements);
    }

    /**
     * Get movements for a specific product
     */
    public function productMovements(Request $request, int $productId): AnonymousResourceCollection
    {
        $filters = $request->only([
            'warehouse_id',
            'movement_type',
            'start_date',
            'end_date',
        ]);
        $perPage = $request->get('per_page', 15);

        $movements = $this->movementService->getProductMovements($productId, $filters, $perPage);

        return StockMovementResource::collection($movements);
    }

    /**
     * Get movements for a specific warehouse
     */
    public function warehouseMovements(Request $request, int $warehouseId): AnonymousResourceCollection
    {
        $filters = $request->only([
            'product_id',
            'movement_type',
            'start_date',
            'end_date',
        ]);
        $perPage = $request->get('per_page', 15);

        $movements = $this->movementService->getWarehouseMovements($warehouseId, $filters, $perPage);

        return StockMovementResource::collection($movements);
    }

    /**
     * Get movement summary
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only([
            'warehouse_id',
            'start_date',
            'end_date',
        ]);

        $summary = $this->movementService->getMovementSummary($filters);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get daily report
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $date = Carbon::parse($validated['date']);
        $warehouseId = $validated['warehouse_id'] ?? null;

        $report = $this->movementService->getDailyReport($date, $warehouseId);

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Get audit trail for a product in a warehouse
     */
    public function auditTrail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $movements = $this->movementService->getAuditTrail(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json([
            'data' => StockMovementResource::collection($movements),
        ]);
    }

    /**
     * Get movement types for dropdown
     */
    public function movementTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->movementService->getMovementTypes(),
        ]);
    }

    /**
     * Get transaction types for dropdown
     */
    public function transactionTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->movementService->getTransactionTypes(),
        ]);
    }
}
