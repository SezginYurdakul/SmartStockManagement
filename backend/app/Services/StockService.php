<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\QualityHoldException;
use App\Enums\ReservationPolicy;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\StockDebt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class StockService
{
    public function __construct(
        protected StockMovementService $movementService,
        protected ?QualityHoldService $qualityHoldService = null
    ) {}

    /**
     * Get paginated stock records with filters
     */
    public function getStocks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Stock::with(['product:id,name,sku', 'warehouse:id,name,code']);

        // Filter by product
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filter by warehouse
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by quality status
        if (!empty($filters['quality_status'])) {
            $query->where('quality_status', $filters['quality_status']);
        }

        // Filter quality available only
        if (!empty($filters['quality_available']) && $filters['quality_available']) {
            $query->qualityAvailable();
        }

        // Filter on quality hold
        if (!empty($filters['on_quality_hold']) && $filters['on_quality_hold']) {
            $query->onQualityHold();
        }

        // Filter by lot number
        if (!empty($filters['lot_number'])) {
            $query->where('lot_number', 'like', "%{$filters['lot_number']}%");
        }

        // Filter low stock
        if (!empty($filters['low_stock']) && $filters['low_stock']) {
            $query->lowStock();
        }

        // Filter expiring soon
        if (!empty($filters['expiring_days'])) {
            $query->expiringSoon((int) $filters['expiring_days']);
        }

        // Filter expired
        if (!empty($filters['expired']) && $filters['expired']) {
            $query->expired();
        }

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Get stock for a specific product
     */
    public function getProductStock(int $productId, ?int $warehouseId = null): array
    {
        $query = Stock::where('product_id', $productId)
            ->with('warehouse:id,name,code');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->get();

        return [
            'total_on_hand' => $stocks->sum('quantity_on_hand'),
            'total_reserved' => $stocks->sum('quantity_reserved'),
            'total_available' => $stocks->sum('quantity_available'),
            'total_value' => $stocks->sum('total_value'),
            'by_warehouse' => $stocks->groupBy('warehouse_id'),
            'stocks' => $stocks,
        ];
    }

    /**
     * Get stock for a specific warehouse
     */
    public function getWarehouseStock(int $warehouseId): array
    {
        $stocks = Stock::where('warehouse_id', $warehouseId)
            ->with('product:id,name,sku')
            ->get();

        return [
            'total_products' => $stocks->unique('product_id')->count(),
            'total_on_hand' => $stocks->sum('quantity_on_hand'),
            'total_reserved' => $stocks->sum('quantity_reserved'),
            'total_available' => $stocks->sum('quantity_available'),
            'total_value' => $stocks->sum('total_value'),
            'stocks' => $stocks,
        ];
    }

    /**
     * Receive stock (inbound)
     */
    public function receiveStock(array $data): Stock
    {
        Log::info('Receiving stock', [
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'quantity' => $data['quantity'],
        ]);

        DB::beginTransaction();

        try {
            // Find or create stock record
            $stock = $this->findOrCreateStock(
                $data['product_id'],
                $data['warehouse_id'],
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null
            );

            $quantityBefore = $stock->quantity_on_hand;

            // Update stock
            $stock->quantity_on_hand += $data['quantity'];
            $stock->unit_cost = $data['unit_cost'] ?? $stock->unit_cost;
            $stock->expiry_date = $data['expiry_date'] ?? $stock->expiry_date;
            $stock->received_date = $data['received_date'] ?? now();
            $stock->status = $data['status'] ?? Stock::STATUS_AVAILABLE;
            $stock->save();

            // If there is stock debt, automatically reconcile it
            $this->reconcileStockDebts($stock, $data['quantity']);

            // Create movement record
            $this->movementService->createMovement([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'movement_type' => StockMovement::TYPE_RECEIPT,
                'transaction_type' => $data['transaction_type'] ?? StockMovement::TRANS_PURCHASE_ORDER,
                'reference_number' => $data['reference_number'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'quantity' => $data['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity_on_hand,
                'unit_cost' => $data['unit_cost'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            DB::commit();

            Log::info('Stock received successfully', [
                'stock_id' => $stock->id,
                'new_quantity' => $stock->quantity_on_hand,
            ]);

            return $stock->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to receive stock', [
                'product_id' => $data['product_id'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Issue stock (outbound)
     */
    public function issueStock(array $data): Stock
    {
        Log::info('Issuing stock', [
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'quantity' => $data['quantity'],
        ]);

        DB::beginTransaction();

        try {
            // Find stock record
            $stock = $this->findStock(
                $data['product_id'],
                $data['warehouse_id'],
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null
            );

            // If stock not found, check if we can create it (for negative stock with ALLOWED policy)
            if (!$stock) {
                $product = Product::findOrFail($data['product_id']);
                $policy = $product->negative_stock_policy ?? 'NEVER';
                
                // Only allow creating stock if policy is ALLOWED or LIMITED
                if ($policy === 'NEVER') {
                    throw new BusinessException("Stock not found for the specified product and warehouse.");
                }
                
                // Create stock record with 0 quantity (will go negative)
                $stock = $this->findOrCreateStock(
                    $data['product_id'],
                    $data['warehouse_id'],
                    $data['lot_number'] ?? null,
                    $data['serial_number'] ?? null
                );
            }

            // Check quality status for sale operation (unless skip_quality_check is set)
            if (empty($data['skip_quality_check'])) {
                $operation = $data['operation_type'] ?? Stock::OPERATION_SALE;
                $this->validateQualityStatus($stock, $operation);
            }

            // Lock for update (atomicity)
            $stock = Stock::where('id', $stock->id)
                ->lockForUpdate()
                ->first();

            $quantityBefore = $stock->quantity_on_hand;
            $quantityAfter = $quantityBefore - $data['quantity'];

            // Load product with fresh data
            $product = Product::findOrFail($stock->product_id);
            
            // Check if we have enough stock or can go negative
            if ($stock->quantity_available < $data['quantity']) {
                // We don't have enough stock - check if we can go negative
                $negativeAmount = $data['quantity'] - $stock->quantity_available;
                
                if (!$this->canGoNegative($product, $negativeAmount)) {
                    $policy = $product->negative_stock_policy ?? 'NEVER';
                    throw new BusinessException(
                        "Insufficient stock. Available: {$stock->quantity_available}, Requested: {$data['quantity']}. " .
                        "Product policy: {$policy}"
                    );
                }
                
                // We can go negative - quantityAfter will be negative
                // Stock debt will be created below
            }

            // Update stock
            $stock->quantity_on_hand = $quantityAfter;
            $stock->save();

            // Create movement record
            $movement = $this->movementService->createMovement([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'movement_type' => StockMovement::TYPE_ISSUE,
                'transaction_type' => $data['transaction_type'] ?? StockMovement::TRANS_SALES_ORDER,
                'reference_number' => $data['reference_number'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'quantity' => -$data['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $stock->unit_cost,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create stock debt if going negative
            if ($quantityAfter < 0) {
                $this->createStockDebt($stock, abs($quantityAfter), $data, $movement);
            }

            DB::commit();

            Log::info('Stock issued successfully', [
                'stock_id' => $stock->id,
                'new_quantity' => $stock->quantity_on_hand,
            ]);

            return $stock->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to issue stock', [
                'product_id' => $data['product_id'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock(array $data): array
    {
        Log::info('Transferring stock', [
            'product_id' => $data['product_id'],
            'from_warehouse_id' => $data['from_warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'quantity' => $data['quantity'],
        ]);

        if ($data['from_warehouse_id'] === $data['to_warehouse_id']) {
            throw new BusinessException("Source and destination warehouses must be different.");
        }

        DB::beginTransaction();

        try {
            // Find source stock
            $sourceStock = $this->findStock(
                $data['product_id'],
                $data['from_warehouse_id'],
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null
            );

            if (!$sourceStock) {
                throw new BusinessException("Stock not found in source warehouse.");
            }

            // Check quality status for transfer operation (unless skip_quality_check is set)
            if (empty($data['skip_quality_check'])) {
                $this->validateQualityStatus($sourceStock, Stock::OPERATION_TRANSFER);
            }

            // Check available quantity
            if ($sourceStock->quantity_available < $data['quantity']) {
                throw new BusinessException(
                    "Insufficient stock in source warehouse. Available: {$sourceStock->quantity_available}"
                );
            }

            // Find or create destination stock
            $destStock = $this->findOrCreateStock(
                $data['product_id'],
                $data['to_warehouse_id'],
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null
            );

            $sourceQuantityBefore = $sourceStock->quantity_on_hand;
            $destQuantityBefore = $destStock->quantity_on_hand;

            // Update source stock
            $sourceStock->quantity_on_hand -= $data['quantity'];
            $sourceStock->save();

            // Update destination stock
            $destStock->quantity_on_hand += $data['quantity'];
            $destStock->unit_cost = $data['unit_cost'] ?? $sourceStock->unit_cost;
            $destStock->expiry_date = $sourceStock->expiry_date;
            $destStock->save();

            // Create transfer-out movement
            $this->movementService->createMovement([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['from_warehouse_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'movement_type' => StockMovement::TYPE_TRANSFER,
                'transaction_type' => StockMovement::TRANS_TRANSFER_ORDER,
                'reference_number' => $data['reference_number'] ?? null,
                'quantity' => -$data['quantity'],
                'quantity_before' => $sourceQuantityBefore,
                'quantity_after' => $sourceStock->quantity_on_hand,
                'unit_cost' => $sourceStock->unit_cost,
                'notes' => $data['notes'] ?? "Transfer to warehouse ID: {$data['to_warehouse_id']}",
            ]);

            // Create transfer-in movement
            $this->movementService->createMovement([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['to_warehouse_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'movement_type' => StockMovement::TYPE_TRANSFER,
                'transaction_type' => StockMovement::TRANS_TRANSFER_ORDER,
                'reference_number' => $data['reference_number'] ?? null,
                'quantity' => $data['quantity'],
                'quantity_before' => $destQuantityBefore,
                'quantity_after' => $destStock->quantity_on_hand,
                'unit_cost' => $destStock->unit_cost,
                'notes' => $data['notes'] ?? "Transfer from warehouse ID: {$data['from_warehouse_id']}",
            ]);

            DB::commit();

            Log::info('Stock transferred successfully', [
                'source_stock_id' => $sourceStock->id,
                'dest_stock_id' => $destStock->id,
            ]);

            return [
                'source' => $sourceStock->fresh(),
                'destination' => $destStock->fresh(),
            ];

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to transfer stock', [
                'product_id' => $data['product_id'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Adjust stock (inventory adjustment)
     */
    public function adjustStock(array $data): Stock
    {
        Log::info('Adjusting stock', [
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'new_quantity' => $data['new_quantity'],
        ]);

        DB::beginTransaction();

        try {
            // Find or create stock record
            $stock = $this->findOrCreateStock(
                $data['product_id'],
                $data['warehouse_id'],
                $data['lot_number'] ?? null,
                $data['serial_number'] ?? null
            );

            $quantityBefore = $stock->quantity_on_hand;
            $difference = $data['new_quantity'] - $quantityBefore;

            if ($difference == 0) {
                return $stock;
            }

            // Update stock
            $stock->quantity_on_hand = $data['new_quantity'];
            $stock->unit_cost = $data['unit_cost'] ?? $stock->unit_cost;
            $stock->save();

            // Create adjustment movement
            $this->movementService->createMovement([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'lot_number' => $data['lot_number'] ?? null,
                'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                'transaction_type' => StockMovement::TRANS_ADJUSTMENT,
                'reference_number' => $data['reference_number'] ?? 'ADJ-' . now()->format('YmdHis'),
                'quantity' => $difference,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity_on_hand,
                'unit_cost' => $stock->unit_cost,
                'notes' => $data['reason'] ?? $data['notes'] ?? 'Stock adjustment',
            ]);

            DB::commit();

            Log::info('Stock adjusted successfully', [
                'stock_id' => $stock->id,
                'old_quantity' => $quantityBefore,
                'new_quantity' => $stock->quantity_on_hand,
                'difference' => $difference,
            ]);

            return $stock->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to adjust stock', [
                'product_id' => $data['product_id'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Reserve stock
     */
    public function reserveStock(
        int $productId,
        int $warehouseId,
        float $quantity,
        ?string $lotNumber = null,
        string $operation = Stock::OPERATION_SALE,
        bool $skipQualityCheck = false
    ): Stock {
        $stock = $this->findStock($productId, $warehouseId, $lotNumber);

        if (!$stock) {
            throw new BusinessException("Stock not found.");
        }

        // Check quality status for the operation
        if (!$skipQualityCheck) {
            $this->validateQualityStatus($stock, $operation);
        }

        // Get product and reservation policy
        $product = Product::findOrFail($productId);
        $policy = ReservationPolicy::tryFrom($product->reservation_policy ?? 'full') ?? ReservationPolicy::FULL;

        $availableQty = $stock->quantity_available;
        $requestedQty = $quantity;

        // Check if we have enough stock
        if ($availableQty < $requestedQty) {
            // Handle based on reservation policy
            if ($policy->shouldReject()) {
                throw new BusinessException(
                    "Cannot reserve {$requestedQty} units. Available: {$availableQty}. " .
                    "Policy: {$policy->label()} (requires full quantity)."
                );
            }

            // PARTIAL policy: reserve what's available
            if ($policy->allowsPartial()) {
                $quantity = $availableQty; // Reserve only available quantity
                Log::info('Partial reservation applied', [
                    'product_id' => $productId,
                    'requested' => $requestedQty,
                    'reserved' => $availableQty,
                    'policy' => $policy->value,
                ]);
            }

            // WAIT policy: TODO - Future implementation
            // This policy should queue the reservation request and automatically retry
            // when stock becomes available (e.g., via stock receipt webhook/event).
            // Requires: Queue system, event listeners, retry mechanism.
            if ($policy === ReservationPolicy::WAIT) {
                throw new BusinessException(
                    "Insufficient stock. Available: {$availableQty}, Requested: {$requestedQty}. " .
                    "Policy: {$policy->label()} - ⚠️ WAIT policy is not yet implemented. " .
                    "Currently throws error. Future: Will queue and auto-retry when stock arrives."
                );
            }
        }

        // Attempt reservation
        if (!$stock->reserve($quantity)) {
            throw new BusinessException(
                "Cannot reserve {$quantity} units. Available: {$stock->quantity_available}"
            );
        }

        Log::info('Stock reserved', [
            'stock_id' => $stock->id,
            'quantity' => $quantity,
            'policy' => $policy->value,
        ]);

        return $stock->fresh();
    }

    /**
     * Release reserved stock
     */
    public function releaseReservation(int $productId, int $warehouseId, float $quantity, ?string $lotNumber = null): Stock
    {
        $stock = $this->findStock($productId, $warehouseId, $lotNumber);

        if (!$stock) {
            throw new BusinessException("Stock not found.");
        }

        if (!$stock->releaseReservation($quantity)) {
            throw new BusinessException(
                "Cannot release {$quantity} units. Reserved: {$stock->quantity_reserved}"
            );
        }

        Log::info('Stock reservation released', [
            'stock_id' => $stock->id,
            'quantity' => $quantity,
        ]);

        return $stock->fresh();
    }

    /**
     * Find existing stock record
     */
    protected function findStock(
        int $productId,
        int $warehouseId,
        ?string $lotNumber = null,
        ?string $serialNumber = null
    ): ?Stock {
        return Stock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('lot_number', $lotNumber)
            ->where('serial_number', $serialNumber)
            ->first();
    }

    /**
     * Find or create stock record
     */
    protected function findOrCreateStock(
        int $productId,
        int $warehouseId,
        ?string $lotNumber = null,
        ?string $serialNumber = null
    ): Stock {
        return Stock::firstOrCreate(
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'lot_number' => $lotNumber,
                'serial_number' => $serialNumber,
            ],
            [
                'company_id' => Auth::user()->company_id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'unit_cost' => 0,
                'status' => Stock::STATUS_AVAILABLE,
            ]
        );
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $perPage = 15): LengthAwarePaginator
    {
        $companyId = Auth::user()->company_id;

        // Get products where total stock across all warehouses is below threshold
        $lowStockProductIds = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('low_stock_threshold')
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(function ($product) {
                $totalStock = $product->getTotalStock();
                return $totalStock <= $product->low_stock_threshold;
            })
            ->pluck('id');

        // Get all stock records for these products
        return Stock::with(['product:id,name,sku,low_stock_threshold', 'warehouse:id,name,code'])
            ->whereIn('product_id', $lowStockProductIds)
            ->orderBy('product_id')
            ->orderBy('warehouse_id')
            ->paginate($perPage);
    }

    /**
     * Get expiring stock
     */
    public function getExpiringStock(int $days = 30, int $perPage = 15): LengthAwarePaginator
    {
        return Stock::with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->expiringSoon($days)
            ->orderBy('expiry_date')
            ->paginate($perPage);
    }

    /**
     * Validate quality status for an operation
     *
     * @throws QualityHoldException
     */
    protected function validateQualityStatus(Stock $stock, string $operation): void
    {
        if ($this->qualityHoldService) {
            $this->qualityHoldService->validateOperation($stock, $operation);
        } elseif (!$stock->isOperationAllowed($operation)) {
            // Fallback if QualityHoldService is not injected
            $qualityStatus = $stock->quality_status ?? Stock::QUALITY_AVAILABLE;
            $statusLabel = Stock::QUALITY_STATUSES[$qualityStatus] ?? $qualityStatus;

            throw new QualityHoldException(
                "Operation '{$operation}' is not allowed for stock with quality status '{$statusLabel}'",
                [
                    'stock_id' => $stock->id,
                    'quality_status' => $qualityStatus,
                    'operation' => $operation,
                    'blocked_operations' => $stock->getBlockedOperations(),
                ]
            );
        }
    }

    /**
     * Get stocks on quality hold
     */
    public function getStocksOnQualityHold(int $perPage = 15): LengthAwarePaginator
    {
        return Stock::with(['product:id,name,sku', 'warehouse:id,name,code', 'qualityHoldBy:id,first_name,last_name'])
            ->onQualityHold()
            ->orderBy('quality_hold_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get stocks by quality status
     */
    public function getStocksByQualityStatus(string $qualityStatus, int $perPage = 15): LengthAwarePaginator
    {
        return Stock::with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->where('quality_status', $qualityStatus)
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check if product can go negative
     */
    protected function canGoNegative(Product $product, float $negativeAmount): bool
    {
        $policy = $product->negative_stock_policy ?? 'NEVER';
        
        if ($policy === 'NEVER') {
            return false;
        }
        
        if ($policy === 'ALLOWED') {
            return true;
        }
        
        if ($policy === 'LIMITED') {
            $limit = $product->negative_stock_limit ?? 0;
            $stockData = $this->getProductStock($product->id, null);
            $currentStock = $stockData['total_available'] ?? 0;
            $currentNegative = max(0, -$currentStock);
            return ($currentNegative + $negativeAmount) <= $limit;
        }
        
        return false;
    }

    /**
     * Create stock debt record
     */
    protected function createStockDebt(Stock $stock, float $debtQuantity, array $data, ?StockMovement $movement = null): void
    {
        StockDebt::create([
            'company_id' => $stock->company_id,
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'stock_movement_id' => $movement?->id,
            'quantity' => $debtQuantity,
            'reconciled_quantity' => 0,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
        ]);

        Log::info('Stock debt created', [
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'debt_quantity' => $debtQuantity,
        ]);
    }

    /**
     * Reconcile stock debts when stock is received
     */
    protected function reconcileStockDebts(Stock $stock, float $receivedQuantity): void
    {
        $debts = StockDebt::where('company_id', $stock->company_id)
            ->where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->whereColumn('reconciled_quantity', '<', 'quantity')
            ->orderBy('created_at', 'asc') // FIFO
            ->get();
        
        if ($debts->isEmpty()) {
            return;
        }
        
        $remaining = $receivedQuantity;
        
        foreach ($debts as $debt) {
            if ($remaining <= 0) {
                break;
            }
            
            $outstanding = $debt->quantity - $debt->reconciled_quantity;
            $toReconcile = min($remaining, $outstanding);
            
            $debt->reconciled_quantity += $toReconcile;
            if ($debt->reconciled_quantity >= $debt->quantity) {
                $debt->reconciled_at = now();
            }
            $debt->save();
            
            $remaining -= $toReconcile;
            
            Log::info('Stock debt reconciled', [
                'debt_id' => $debt->id,
                'reconciled_quantity' => $toReconcile,
                'remaining_debt' => $debt->quantity - $debt->reconciled_quantity,
            ]);
        }
    }
}
