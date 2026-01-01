<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Http\Resources\WorkOrderResource;
use App\Http\Resources\WorkOrderListResource;
use App\Http\Resources\WorkOrderOperationResource;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderPriority;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Display a listing of work orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'product_id',
            'status',
            'priority',
            'active_only',
            'from_date',
            'to_date',
            'order_by_priority',
        ]);
        $perPage = $request->get('per_page', 15);

        $workOrders = $this->workOrderService->getWorkOrders($filters, $perPage);

        return WorkOrderListResource::collection($workOrders);
    }

    /**
     * Get work order statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->workOrderService->getStatistics();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created work order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'bom_id' => 'nullable|exists:boms,id',
            'routing_id' => 'nullable|exists:routings,id',
            'work_order_number' => 'nullable|string|max:50',
            'quantity_ordered' => 'required|numeric|min:0.001',
            'uom_id' => 'required|exists:units_of_measure,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'priority' => ['nullable', Rule::enum(WorkOrderPriority::class)],
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $workOrder = $this->workOrderService->create($validated);

        return response()->json([
            'message' => 'Work order created successfully',
            'data' => WorkOrderResource::make($workOrder),
        ], 201);
    }

    /**
     * Display the specified work order
     */
    public function show(WorkOrder $workOrder): JsonResource
    {
        return WorkOrderResource::make(
            $this->workOrderService->getWorkOrder($workOrder)
        );
    }

    /**
     * Update the specified work order
     */
    public function update(Request $request, WorkOrder $workOrder): JsonResource
    {
        $validated = $request->validate([
            'quantity_ordered' => 'sometimes|numeric|min:0.001',
            'priority' => ['nullable', Rule::enum(WorkOrderPriority::class)],
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $workOrder = $this->workOrderService->update($workOrder, $validated);

        return WorkOrderResource::make($workOrder)
            ->additional(['message' => 'Work order updated successfully']);
    }

    /**
     * Remove the specified work order
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->workOrderService->delete($workOrder);

        return response()->json([
            'message' => 'Work order deleted successfully',
        ]);
    }

    /**
     * Release work order for production
     */
    public function release(WorkOrder $workOrder): JsonResponse
    {
        $workOrder = $this->workOrderService->release($workOrder);

        return response()->json([
            'message' => 'Work order released successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Start work order
     */
    public function start(WorkOrder $workOrder): JsonResponse
    {
        $workOrder = $this->workOrderService->start($workOrder);

        return response()->json([
            'message' => 'Work order started successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Complete work order
     */
    public function complete(WorkOrder $workOrder): JsonResponse
    {
        $workOrder = $this->workOrderService->complete($workOrder);

        return response()->json([
            'message' => 'Work order completed successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Cancel work order
     */
    public function cancel(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $workOrder = $this->workOrderService->cancel($workOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Work order cancelled successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Put work order on hold
     */
    public function hold(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $workOrder = $this->workOrderService->hold($workOrder, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Work order put on hold successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Resume work order from hold
     */
    public function resume(WorkOrder $workOrder): JsonResponse
    {
        $workOrder = $this->workOrderService->resume($workOrder);

        return response()->json([
            'message' => 'Work order resumed successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Start an operation
     */
    public function startOperation(WorkOrder $workOrder, int $operationId): JsonResponse
    {
        $operation = $this->workOrderService->startOperation($workOrder, $operationId);

        return response()->json([
            'message' => 'Operation started successfully',
            'data' => WorkOrderOperationResource::make($operation),
        ]);
    }

    /**
     * Complete an operation
     */
    public function completeOperation(Request $request, WorkOrder $workOrder, int $operationId): JsonResponse
    {
        $validated = $request->validate([
            'quantity_completed' => 'required|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'actual_setup_time' => 'nullable|numeric|min:0',
            'actual_run_time' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $operation = $this->workOrderService->completeOperation(
            $workOrder,
            $operationId,
            $validated['quantity_completed'],
            $validated['quantity_scrapped'] ?? 0,
            $validated['actual_setup_time'] ?? null,
            $validated['actual_run_time'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Operation completed successfully',
            'data' => WorkOrderOperationResource::make($operation),
        ]);
    }

    /**
     * Get material requirements
     */
    public function materialRequirements(WorkOrder $workOrder): JsonResponse
    {
        $requirements = $this->workOrderService->getMaterialRequirements($workOrder);

        return response()->json([
            'data' => $requirements,
        ]);
    }

    /**
     * Issue materials
     */
    public function issueMaterials(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'material_ids' => 'nullable|array',
            'material_ids.*' => 'integer|exists:work_order_materials,id',
        ]);

        $workOrder = $this->workOrderService->issueMaterials(
            $workOrder,
            $validated['material_ids'] ?? null
        );

        return response()->json([
            'message' => 'Materials issued successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Receive finished goods
     */
    public function receiveFinishedGoods(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
            'lot_number' => 'nullable|string|max:100',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $workOrder = $this->workOrderService->receiveFinishedGoods(
            $workOrder,
            $validated['quantity'],
            $validated['lot_number'] ?? null,
            $validated['unit_cost'] ?? null
        );

        return response()->json([
            'message' => 'Finished goods received successfully',
            'data' => WorkOrderResource::make($workOrder),
        ]);
    }

    /**
     * Get work order statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => WorkOrderStatus::options(),
        ]);
    }

    /**
     * Get work order priorities
     */
    public function priorities(): JsonResponse
    {
        return response()->json([
            'data' => WorkOrderPriority::options(),
        ]);
    }
}
