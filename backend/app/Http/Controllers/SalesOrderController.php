<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use App\Http\Resources\SalesOrderResource;
use App\Http\Resources\SalesOrderListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderController extends Controller
{
    public function __construct(
        protected SalesOrderService $salesOrderService
    ) {}

    /**
     * Display a listing of sales orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'customer_id',
            'from_date',
            'to_date',
            'pending_approval',
        ]);
        $perPage = $request->get('per_page', 15);

        $salesOrders = $this->salesOrderService->getSalesOrders($filters, $perPage);

        return SalesOrderListResource::collection($salesOrders);
    }

    /**
     * Store a newly created sales order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $salesOrder = $this->salesOrderService->create($validated);

        return response()->json([
            'message' => 'Sales order created successfully',
            'data' => SalesOrderResource::make($salesOrder),
        ], 201);
    }

    /**
     * Display the specified sales order
     */
    public function show(SalesOrder $salesOrder): JsonResource
    {
        return SalesOrderResource::make(
            $this->salesOrderService->getSalesOrder($salesOrder)
        );
    }

    /**
     * Update the specified sales order
     */
    public function update(Request $request, SalesOrder $salesOrder): JsonResource
    {
        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $salesOrder = $this->salesOrderService->update($salesOrder, $validated);

        return SalesOrderResource::make($salesOrder)
            ->additional(['message' => 'Sales order updated successfully']);
    }

    /**
     * Remove the specified sales order
     */
    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        $this->salesOrderService->delete($salesOrder);

        return response()->json([
            'message' => 'Sales order deleted successfully',
        ]);
    }

    /**
     * Submit order for approval
     */
    public function submitForApproval(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->submitForApproval($salesOrder);

        return response()->json([
            'message' => 'Sales order submitted for approval',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Approve sales order
     */
    public function approve(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->approve($salesOrder);

        return response()->json([
            'message' => 'Sales order approved',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Reject sales order
     */
    public function reject(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $salesOrder = $this->salesOrderService->reject($salesOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Sales order rejected',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Confirm sales order
     */
    public function confirm(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->confirm($salesOrder);

        return response()->json([
            'message' => 'Sales order confirmed',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Mark order as shipped
     */
    public function markAsShipped(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->markAsShipped($salesOrder);

        return response()->json([
            'message' => 'Sales order marked as shipped',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Mark order as delivered
     */
    public function markAsDelivered(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder = $this->salesOrderService->markAsDelivered($salesOrder);

        return response()->json([
            'message' => 'Sales order marked as delivered',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Cancel sales order
     */
    public function cancel(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $salesOrder = $this->salesOrderService->cancel($salesOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Sales order cancelled',
            'data' => SalesOrderResource::make($salesOrder),
        ]);
    }

    /**
     * Get sales order statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        $stats = $this->salesOrderService->getStatistics($filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get available statuses
     */
    public function statuses(): JsonResponse
    {
        $statuses = $this->salesOrderService->getStatuses();

        return response()->json([
            'data' => $statuses,
        ]);
    }
}
