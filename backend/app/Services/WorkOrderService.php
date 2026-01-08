<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Models\WorkOrderOperation;
use App\Models\WorkOrderMaterial;
use App\Models\Bom;
use App\Models\Routing;
use App\Models\Product;
use App\Models\Stock;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderPriority;
use App\Enums\OperationStatus;
use App\Exceptions\BusinessException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class WorkOrderService
{
    public function __construct(
        protected BomService $bomService,
        protected RoutingService $routingService,
        protected StockService $stockService
    ) {}

    /**
     * Get paginated work orders with filters
     */
    public function getWorkOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WorkOrder::with([
            'product:id,name,sku',
            'warehouse:id,name,code',
            'creator:id,first_name,last_name',
        ]);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Date range filter
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->plannedBetween($filters['from_date'], $filters['to_date']);
        }

        // Order by priority and status
        if (!empty($filters['order_by_priority'])) {
            $query->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'low' THEN 4
                END
            ");
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get work order with full relationships
     */
    public function getWorkOrder(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->load([
            'product:id,name,sku',
            'bom:id,bom_number,name',
            'routing:id,routing_number,name',
            'warehouse:id,name,code',
            'uom:id,code,name',
            'creator:id,first_name,last_name',
            'approver:id,first_name,last_name',
            'releaser:id,first_name,last_name',
            'operations.workCenter:id,code,name',
            'materials.product:id,name,sku',
            'materials.uom:id,code,name',
        ]);
    }

    /**
     * Create a new work order
     */
    public function create(array $data): WorkOrder
    {
        Log::info('Creating work order', [
            'product_id' => $data['product_id'] ?? null,
            'quantity' => $data['quantity_ordered'] ?? null,
        ]);

        return DB::transaction(function () use ($data) {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();

            // Generate work order number if not provided
            if (empty($data['work_order_number'])) {
                $data['work_order_number'] = $this->generateWorkOrderNumber();
            }

            // Get default BOM if not specified
            if (empty($data['bom_id'])) {
                $defaultBom = $this->bomService->getDefaultBomForProduct($data['product_id']);
                $data['bom_id'] = $defaultBom?->id;
            }

            // Get default routing if not specified
            if (empty($data['routing_id'])) {
                $defaultRouting = $this->routingService->getDefaultRoutingForProduct($data['product_id']);
                $data['routing_id'] = $defaultRouting?->id;
            }

            $workOrder = WorkOrder::create($data);

            // Copy operations from routing
            if ($workOrder->routing_id) {
                $this->copyOperationsFromRouting($workOrder);
            }

            // Calculate material requirements from BOM
            if ($workOrder->bom_id) {
                $this->calculateMaterialRequirements($workOrder);
            }

            // Calculate estimated cost
            $this->calculateEstimatedCost($workOrder);

            Log::info('Work order created', [
                'id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
            ]);

            return $workOrder->fresh(['operations', 'materials']);
        });
    }

    /**
     * Update work order
     */
    public function update(WorkOrder $workOrder, array $data): WorkOrder
    {
        if (!$workOrder->canEdit()) {
            throw new BusinessException("Work order cannot be edited in {$workOrder->status->label()} status.");
        }

        Log::info('Updating work order', [
            'id' => $workOrder->id,
            'changes' => array_keys($data),
        ]);

        $workOrder->update($data);

        // Recalculate materials if quantity changed
        if (isset($data['quantity_ordered']) && $workOrder->bom_id) {
            $this->calculateMaterialRequirements($workOrder);
        }

        return $workOrder->fresh();
    }

    /**
     * Delete work order
     */
    public function delete(WorkOrder $workOrder): bool
    {
        if ($workOrder->status !== WorkOrderStatus::DRAFT) {
            throw new BusinessException("Only draft work orders can be deleted.");
        }

        Log::info('Deleting work order', ['id' => $workOrder->id]);

        return $workOrder->delete();
    }

    /**
     * Release work order for production
     * Automatically reserves materials for all items
     */
    public function release(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->canRelease()) {
            throw new BusinessException("Work order cannot be released from {$workOrder->status->label()} status.");
        }

        Log::info('Releasing work order', ['id' => $workOrder->id]);

        DB::beginTransaction();

        try {
            $workOrder->update([
                'status' => WorkOrderStatus::RELEASED,
                'released_by' => Auth::id(),
                'released_at' => now(),
            ]);

            // Automatically reserve materials for all items
            $this->reserveMaterialsForOrder($workOrder);

            DB::commit();

            Log::info('Work order released and materials reserved', [
                'work_order_id' => $workOrder->id,
            ]);

            return $workOrder->fresh(['materials.product']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to release work order', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Start work order
     */
    public function start(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->canStart()) {
            throw new BusinessException("Work order cannot be started from {$workOrder->status->label()} status.");
        }

        Log::info('Starting work order', ['id' => $workOrder->id]);

        $workOrder->update([
            'status' => WorkOrderStatus::IN_PROGRESS,
            'actual_start_date' => $workOrder->actual_start_date ?? now(),
        ]);

        return $workOrder->fresh();
    }

    /**
     * Complete work order
     */
    public function complete(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->canComplete()) {
            throw new BusinessException("Work order cannot be completed from {$workOrder->status->label()} status.");
        }

        // Check if all operations are completed
        if (!$workOrder->allOperationsCompleted()) {
            throw new BusinessException("All operations must be completed before completing the work order.");
        }

        Log::info('Completing work order', ['id' => $workOrder->id]);

        $workOrder->update([
            'status' => WorkOrderStatus::COMPLETED,
            'actual_end_date' => now(),
            'completed_by' => Auth::id(),
            'completed_at' => now(),
        ]);

        return $workOrder->fresh();
    }

    /**
     * Cancel work order
     * Releases any reserved materials if order was released
     */
    public function cancel(WorkOrder $workOrder, ?string $reason = null): WorkOrder
    {
        if (!$workOrder->canCancel()) {
            throw new BusinessException("Work order cannot be cancelled from {$workOrder->status->label()} status.");
        }

        Log::info('Cancelling work order', ['id' => $workOrder->id, 'reason' => $reason]);

        DB::beginTransaction();

        try {
            // Release reserved materials if order was released
            if (in_array($workOrder->status, [WorkOrderStatus::RELEASED, WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::ON_HOLD])) {
                $this->releaseMaterialsForOrder($workOrder);
            }

            $workOrder->update([
                'status' => WorkOrderStatus::CANCELLED,
                'notes' => $reason ? ($workOrder->notes . "\n\nCancelled: " . $reason) : $workOrder->notes,
            ]);

            DB::commit();

            return $workOrder->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel work order', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Put work order on hold
     */
    public function hold(WorkOrder $workOrder, ?string $reason = null): WorkOrder
    {
        if (!$workOrder->status->canHold()) {
            throw new BusinessException("Work order cannot be put on hold from {$workOrder->status->label()} status.");
        }

        Log::info('Putting work order on hold', ['id' => $workOrder->id, 'reason' => $reason]);

        $workOrder->update([
            'status' => WorkOrderStatus::ON_HOLD,
            'notes' => $reason ? ($workOrder->notes . "\n\nOn hold: " . $reason) : $workOrder->notes,
        ]);

        return $workOrder->fresh();
    }

    /**
     * Resume work order from hold
     */
    public function resume(WorkOrder $workOrder): WorkOrder
    {
        if ($workOrder->status !== WorkOrderStatus::ON_HOLD) {
            throw new BusinessException("Only on-hold work orders can be resumed.");
        }

        // Determine previous state
        $newStatus = $workOrder->actual_start_date
            ? WorkOrderStatus::IN_PROGRESS
            : WorkOrderStatus::RELEASED;

        Log::info('Resuming work order', ['id' => $workOrder->id, 'new_status' => $newStatus->value]);

        $workOrder->update(['status' => $newStatus]);

        return $workOrder->fresh();
    }

    /**
     * Start an operation
     */
    public function startOperation(WorkOrder $workOrder, int $operationId): WorkOrderOperation
    {
        if (!$workOrder->status->isActive()) {
            throw new BusinessException("Cannot start operations on inactive work order.");
        }

        $operation = $workOrder->operations()->findOrFail($operationId);

        if (!$operation->canStart()) {
            throw new BusinessException("Operation cannot be started from {$operation->status->label()} status.");
        }

        Log::info('Starting operation', [
            'work_order_id' => $workOrder->id,
            'operation_id' => $operationId,
        ]);

        $operation->update([
            'status' => OperationStatus::IN_PROGRESS,
            'actual_start' => now(),
            'started_by' => Auth::id(),
        ]);

        // Start work order if not already started
        if ($workOrder->status === WorkOrderStatus::RELEASED) {
            $this->start($workOrder);
        }

        return $operation->fresh();
    }

    /**
     * Complete an operation
     */
    public function completeOperation(
        WorkOrder $workOrder,
        int $operationId,
        float $quantityCompleted,
        float $quantityScrapped = 0,
        ?float $actualSetupTime = null,
        ?float $actualRunTime = null,
        ?string $notes = null
    ): WorkOrderOperation {
        $operation = $workOrder->operations()->findOrFail($operationId);

        if (!$operation->canComplete()) {
            throw new BusinessException("Operation cannot be completed from {$operation->status->label()} status.");
        }

        Log::info('Completing operation', [
            'work_order_id' => $workOrder->id,
            'operation_id' => $operationId,
            'quantity_completed' => $quantityCompleted,
        ]);

        $operation->update([
            'status' => OperationStatus::COMPLETED,
            'quantity_completed' => $quantityCompleted,
            'quantity_scrapped' => $quantityScrapped,
            'actual_end' => now(),
            'actual_setup_time' => $actualSetupTime ?? $operation->actual_setup_time,
            'actual_run_time' => $actualRunTime ?? $operation->actual_run_time,
            'notes' => $notes,
            'completed_by' => Auth::id(),
        ]);

        // Calculate operation cost
        $this->calculateOperationCost($operation->fresh());

        return $operation->fresh();
    }

    /**
     * Get material requirements for work order
     */
    public function getMaterialRequirements(WorkOrder $workOrder): array
    {
        $materials = $workOrder->materials()
            ->with(['product:id,name,sku', 'uom:id,code,name', 'warehouse:id,name,code'])
            ->get();

        $requirements = [];

        foreach ($materials as $material) {
            // Get available stock
            $availableStock = Stock::where('product_id', $material->product_id)
                ->where('warehouse_id', $material->warehouse_id)
                ->qualityAvailable()
                ->sum('quantity_available');

            $requirements[] = [
                'material' => $material,
                'available_stock' => $availableStock,
                'shortage' => max(0, $material->outstanding_quantity - $availableStock),
            ];
        }

        return $requirements;
    }

    /**
     * Issue materials for work order
     */
    public function issueMaterials(WorkOrder $workOrder, ?array $materialIds = null): WorkOrder
    {
        if (!$workOrder->canIssueMaterials()) {
            throw new BusinessException("Materials cannot be issued in {$workOrder->status->label()} status.");
        }

        Log::info('Issuing materials for work order', ['id' => $workOrder->id]);

        return DB::transaction(function () use ($workOrder, $materialIds) {
            $query = $workOrder->materials()->withOutstanding();

            if ($materialIds) {
                $query->whereIn('id', $materialIds);
            }

            $materials = $query->get();

            foreach ($materials as $material) {
                $this->issueMaterial($workOrder, $material);
            }

            return $workOrder->fresh(['materials']);
        });
    }

    /**
     * Issue single material
     * Releases reservation and issues physical stock
     */
    protected function issueMaterial(WorkOrder $workOrder, WorkOrderMaterial $material): void
    {
        $outstandingQty = $material->outstanding_quantity;

        if ($outstandingQty <= 0) {
            return;
        }

        // Release reservation first (physical stock is being issued)
        try {
            $this->stockService->releaseReservation(
                $material->product_id,
                $material->warehouse_id,
                $outstandingQty,
                null // lot_number
            );
        } catch (BusinessException $e) {
            // If reservation doesn't exist or is less, log warning but continue
            Log::warning('Could not release reservation for work order material', [
                'work_order_id' => $workOrder->id,
                'material_id' => $material->id,
                'product_id' => $material->product_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Issue stock
        $this->stockService->issueStock([
            'product_id' => $material->product_id,
            'warehouse_id' => $material->warehouse_id,
            'quantity' => $outstandingQty,
            'operation_type' => Stock::OPERATION_PRODUCTION,
            'transaction_type' => 'production_order',
            'reference_type' => WorkOrder::class,
            'reference_id' => $workOrder->id,
            'notes' => "Material issued for WO: {$workOrder->work_order_number}",
        ]);

        // Update material record
        $material->update([
            'quantity_issued' => $material->quantity_issued + $outstandingQty,
        ]);

        Log::info('Material issued', [
            'work_order_id' => $workOrder->id,
            'product_id' => $material->product_id,
            'quantity' => $outstandingQty,
        ]);
    }

    /**
     * Receive finished goods
     */
    public function receiveFinishedGoods(
        WorkOrder $workOrder,
        float $quantity,
        ?string $lotNumber = null,
        ?float $unitCost = null
    ): WorkOrder {
        if (!$workOrder->canReceiveFinishedGoods()) {
            throw new BusinessException("Finished goods cannot be received in {$workOrder->status->label()} status.");
        }

        $maxReceivable = $workOrder->remaining_quantity;

        if ($quantity > $maxReceivable) {
            throw new BusinessException("Cannot receive more than remaining quantity ({$maxReceivable}).");
        }

        Log::info('Receiving finished goods', [
            'work_order_id' => $workOrder->id,
            'quantity' => $quantity,
        ]);

        return DB::transaction(function () use ($workOrder, $quantity, $lotNumber, $unitCost) {
            // Calculate unit cost if not provided
            if ($unitCost === null) {
                $unitCost = $this->calculateUnitCost($workOrder);
            }

            // Receive stock
            $this->stockService->receiveStock([
                'product_id' => $workOrder->product_id,
                'warehouse_id' => $workOrder->warehouse_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'lot_number' => $lotNumber,
                'transaction_type' => 'production_order',
                'reference_type' => WorkOrder::class,
                'reference_id' => $workOrder->id,
                'notes' => "Finished goods from WO: {$workOrder->work_order_number}",
            ]);

            // Update work order
            $workOrder->update([
                'quantity_completed' => $workOrder->quantity_completed + $quantity,
            ]);

            // Auto-complete if all quantity received
            if ($workOrder->fresh()->remaining_quantity <= 0 && $workOrder->allOperationsCompleted()) {
                $this->complete($workOrder);
            }

            return $workOrder->fresh();
        });
    }

    /**
     * Get work order statistics
     */
    public function getStatistics(): array
    {
        $companyId = Auth::user()->company_id;

        $workOrders = WorkOrder::where('company_id', $companyId);

        return [
            'total' => $workOrders->clone()->count(),
            'by_status' => [
                'draft' => $workOrders->clone()->where('status', WorkOrderStatus::DRAFT)->count(),
                'released' => $workOrders->clone()->where('status', WorkOrderStatus::RELEASED)->count(),
                'in_progress' => $workOrders->clone()->where('status', WorkOrderStatus::IN_PROGRESS)->count(),
                'completed' => $workOrders->clone()->where('status', WorkOrderStatus::COMPLETED)->count(),
                'cancelled' => $workOrders->clone()->where('status', WorkOrderStatus::CANCELLED)->count(),
                'on_hold' => $workOrders->clone()->where('status', WorkOrderStatus::ON_HOLD)->count(),
            ],
            'by_priority' => [
                'urgent' => $workOrders->clone()->active()->where('priority', WorkOrderPriority::URGENT)->count(),
                'high' => $workOrders->clone()->active()->where('priority', WorkOrderPriority::HIGH)->count(),
                'normal' => $workOrders->clone()->active()->where('priority', WorkOrderPriority::NORMAL)->count(),
                'low' => $workOrders->clone()->active()->where('priority', WorkOrderPriority::LOW)->count(),
            ],
            'overdue' => $workOrders->clone()
                ->active()
                ->where('planned_end_date', '<', now())
                ->count(),
        ];
    }

    /**
     * Copy operations from routing to work order
     */
    protected function copyOperationsFromRouting(WorkOrder $workOrder): void
    {
        $routing = $workOrder->routing;

        if (!$routing) {
            return;
        }

        foreach ($routing->operations as $routingOp) {
            WorkOrderOperation::create([
                'work_order_id' => $workOrder->id,
                'routing_operation_id' => $routingOp->id,
                'work_center_id' => $routingOp->work_center_id,
                'operation_number' => $routingOp->operation_number,
                'name' => $routingOp->name,
                'description' => $routingOp->description,
                'status' => OperationStatus::PENDING,
            ]);
        }
    }

    /**
     * Calculate material requirements from BOM
     */
    protected function calculateMaterialRequirements(WorkOrder $workOrder): void
    {
        // Clear existing materials
        $workOrder->materials()->delete();

        $bom = $workOrder->bom;

        if (!$bom) {
            return;
        }

        // Explode BOM
        $materials = $this->bomService->explodeBom($bom, $workOrder->quantity_ordered);

        // Create material records
        foreach ($materials as $material) {
            WorkOrderMaterial::create([
                'work_order_id' => $workOrder->id,
                'product_id' => $material['product_id'],
                'bom_item_id' => $material['bom_item_id'],
                'quantity_required' => $material['quantity'],
                'uom_id' => $material['uom_id'],
                'warehouse_id' => $workOrder->warehouse_id,
            ]);
        }
    }

    /**
     * Calculate estimated cost
     */
    protected function calculateEstimatedCost(WorkOrder $workOrder): void
    {
        $materialCost = 0;
        $laborCost = 0;

        // Material cost - use cost_price, fallback to average stock cost
        foreach ($workOrder->materials as $material) {
            $product = $material->product;
            $unitCost = $product->cost_price ?? $product->stocks()->avg('unit_cost') ?? 0;
            $materialCost += $material->quantity_required * $unitCost;
        }

        // Labor cost from routing
        if ($workOrder->routing) {
            foreach ($workOrder->routing->operations as $op) {
                $laborCost += $op->calculateCost($workOrder->quantity_ordered);
            }
        }

        $workOrder->update([
            'estimated_cost' => $materialCost + $laborCost,
        ]);
    }

    /**
     * Calculate operation cost
     */
    protected function calculateOperationCost(WorkOrderOperation $operation): void
    {
        $workCenter = $operation->workCenter;

        if (!$workCenter) {
            return;
        }

        $hours = $operation->total_actual_time / 60;
        $cost = $hours * $workCenter->cost_per_hour;

        $operation->update(['actual_cost' => $cost]);

        // Update work order actual cost
        $workOrder = $operation->workOrder;
        $totalCost = $workOrder->operations()->sum('actual_cost');
        $materialCost = $workOrder->materials()->sum('total_cost');

        $workOrder->update(['actual_cost' => $totalCost + $materialCost]);
    }

    /**
     * Calculate unit cost for finished goods
     */
    protected function calculateUnitCost(WorkOrder $workOrder): float
    {
        if ($workOrder->quantity_completed + $workOrder->quantity_scrapped == 0) {
            return 0;
        }

        return $workOrder->actual_cost / ($workOrder->quantity_completed + $workOrder->quantity_scrapped);
    }

    /**
     * Generate work order number
     */
    public function generateWorkOrderNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $companyIdPadded = str_pad($companyId, 3, '0', STR_PAD_LEFT);
        $prefix = 'WO-' . now()->format('Ym') . "-{$companyIdPadded}-";

        $lastWO = WorkOrder::withTrashed()
            ->where('company_id', $companyId)
            ->where('work_order_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(work_order_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastWO && preg_match('/(\d+)$/', $lastWO->work_order_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Reserve materials for all items in a released work order
     */
    protected function reserveMaterialsForOrder(WorkOrder $workOrder): void
    {
        if (!$workOrder->warehouse_id) {
            Log::warning('Cannot reserve materials: work order has no warehouse', [
                'work_order_id' => $workOrder->id,
            ]);
            return;
        }

        $workOrder->load('materials.product');

        foreach ($workOrder->materials as $material) {
            try {
                $this->stockService->reserveStock(
                    $material->product_id,
                    $material->warehouse_id,
                    $material->quantity_required,
                    null, // lot_number
                    Stock::OPERATION_PRODUCTION,
                    false // skipQualityCheck
                );

                Log::info('Material reserved for work order', [
                    'work_order_id' => $workOrder->id,
                    'material_id' => $material->id,
                    'product_id' => $material->product_id,
                    'quantity' => $material->quantity_required,
                ]);
            } catch (BusinessException $e) {
                Log::error('Failed to reserve material for work order', [
                    'work_order_id' => $workOrder->id,
                    'material_id' => $material->id,
                    'product_id' => $material->product_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other materials even if one fails
            }
        }
    }

    /**
     * Release reserved materials for all items in a work order
     */
    protected function releaseMaterialsForOrder(WorkOrder $workOrder): void
    {
        if (!$workOrder->warehouse_id) {
            Log::warning('Cannot release materials: work order has no warehouse', [
                'work_order_id' => $workOrder->id,
            ]);
            return;
        }

        $workOrder->load('materials.product');

        foreach ($workOrder->materials as $material) {
            try {
                $this->stockService->releaseReservation(
                    $material->product_id,
                    $material->warehouse_id,
                    $material->quantity_required,
                    null // lot_number
                );

                Log::info('Material reservation released for work order', [
                    'work_order_id' => $workOrder->id,
                    'material_id' => $material->id,
                    'product_id' => $material->product_id,
                    'quantity' => $material->quantity_required,
                ]);
            } catch (BusinessException $e) {
                Log::error('Failed to release material reservation for work order', [
                    'work_order_id' => $workOrder->id,
                    'material_id' => $material->id,
                    'product_id' => $material->product_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other materials even if one fails
            }
        }
    }
}
