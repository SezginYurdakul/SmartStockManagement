<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class StockService
{
    public function __construct(
        protected StockMovementService $movementService
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

            throw new BusinessException("Failed to receive stock: {$e->getMessage()}");
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

            if (!$stock) {
                throw new BusinessException("Stock not found for the specified product and warehouse.");
            }

            // Check available quantity
            if ($stock->quantity_available < $data['quantity']) {
                throw new BusinessException(
                    "Insufficient stock. Available: {$stock->quantity_available}, Requested: {$data['quantity']}"
                );
            }

            $quantityBefore = $stock->quantity_on_hand;

            // Update stock
            $stock->quantity_on_hand -= $data['quantity'];
            $stock->save();

            // Create movement record
            $this->movementService->createMovement([
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
                'quantity_after' => $stock->quantity_on_hand,
                'unit_cost' => $stock->unit_cost,
                'notes' => $data['notes'] ?? null,
            ]);

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

            throw new BusinessException("Failed to issue stock: {$e->getMessage()}");
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

            throw new BusinessException("Failed to transfer stock: {$e->getMessage()}");
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

            throw new BusinessException("Failed to adjust stock: {$e->getMessage()}");
        }
    }

    /**
     * Reserve stock
     */
    public function reserveStock(int $productId, int $warehouseId, float $quantity, ?string $lotNumber = null): Stock
    {
        $stock = $this->findStock($productId, $warehouseId, $lotNumber);

        if (!$stock) {
            throw new BusinessException("Stock not found.");
        }

        if (!$stock->reserve($quantity)) {
            throw new BusinessException(
                "Cannot reserve {$quantity} units. Available: {$stock->quantity_available}"
            );
        }

        Log::info('Stock reserved', [
            'stock_id' => $stock->id,
            'quantity' => $quantity,
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
        return Stock::with(['product:id,name,sku,low_stock_threshold', 'warehouse:id,name,code'])
            ->lowStock()
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
}
