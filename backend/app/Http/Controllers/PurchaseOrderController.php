<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseOrderListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Display a listing of purchase orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'supplier_id',
            'warehouse_id',
            'from_date',
            'to_date',
            'overdue',
        ]);
        $perPage = $request->get('per_page', 15);

        $purchaseOrders = $this->purchaseOrderService->getPurchaseOrders($filters, $perPage);

        return PurchaseOrderListResource::collection($purchaseOrders);
    }

    /**
     * Store a newly created purchase order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'nullable|string|max:50',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'payment_due_days' => 'nullable|integer|min:0',
            'shipping_method' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.uom_id' => 'required|exists:units_of_measure,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.expected_delivery_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string',
        ]);

        $purchaseOrder = $this->purchaseOrderService->create($validated);

        return response()->json([
            'message' => 'Purchase order created successfully',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ], 201);
    }

    /**
     * Display the specified purchase order
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResource
    {
        return new PurchaseOrderResource(
            $this->purchaseOrderService->getPurchaseOrder($purchaseOrder)
        );
    }

    /**
     * Update the specified purchase order
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResource
    {
        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'payment_due_days' => 'nullable|integer|min:0',
            'shipping_method' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.uom_id' => 'required|exists:units_of_measure,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.expected_delivery_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string',
        ]);

        $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $validated);

        return (new PurchaseOrderResource($purchaseOrder))
            ->additional(['message' => 'Purchase order updated successfully']);
    }

    /**
     * Remove the specified purchase order
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->purchaseOrderService->delete($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order deleted successfully',
        ]);
    }

    /**
     * Add items to purchase order
     */
    public function addItems(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.uom_id' => 'required|exists:units_of_measure,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.expected_delivery_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string',
        ]);

        $this->purchaseOrderService->addItems($purchaseOrder, $validated['items']);

        return response()->json([
            'message' => 'Items added successfully',
            'data' => new PurchaseOrderResource($purchaseOrder->fresh(['items.product'])),
        ]);
    }

    /**
     * Update a single item
     */
    public function updateItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity_ordered' => 'sometimes|required|numeric|min:0.001',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $this->purchaseOrderService->updateItem($item, $validated);

        return response()->json([
            'message' => 'Item updated successfully',
        ]);
    }

    /**
     * Delete a single item
     */
    public function deleteItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse
    {
        $this->purchaseOrderService->deleteItem($item);

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }

    /**
     * Submit for approval
     */
    public function submitForApproval(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->submitForApproval($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order submitted for approval',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Approve purchase order
     */
    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->approve($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order approved',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Reject purchase order
     */
    public function reject(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $purchaseOrder = $this->purchaseOrderService->reject($purchaseOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Purchase order rejected',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Mark as sent to supplier
     */
    public function markAsSent(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->markAsSent($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order marked as sent',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Cancel purchase order
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $purchaseOrder = $this->purchaseOrderService->cancel($purchaseOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Purchase order cancelled',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Close purchase order
     */
    public function close(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->close($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order closed',
            'data' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        $stats = $this->purchaseOrderService->getStatistics($filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get overdue orders
     */
    public function overdue(): JsonResponse
    {
        $orders = $this->purchaseOrderService->getOverdueOrders();

        return response()->json([
            'data' => PurchaseOrderListResource::collection($orders),
        ]);
    }
}
