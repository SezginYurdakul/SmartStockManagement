<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\StockMovement;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class StockMovementService
{
    /**
     * Get paginated stock movements with filters
     */
    public function getMovements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockMovement::with([
            'product:id,name,sku',
            'warehouse:id,name,code',
            'creator:id,first_name,last_name',
        ]);

        // Filter by product
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filter by warehouse
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Filter by movement type
        if (!empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        // Filter by transaction type
        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        // Filter by reference number
        if (!empty($filters['reference_number'])) {
            $query->where('reference_number', 'like', "%{$filters['reference_number']}%");
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $query->where('movement_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('movement_date', '<=', $filters['end_date']);
        }

        // Filter inbound/outbound
        if (!empty($filters['direction'])) {
            if ($filters['direction'] === 'inbound') {
                $query->inbound();
            } elseif ($filters['direction'] === 'outbound') {
                $query->outbound();
            }
        }

        return $query->orderBy('movement_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get movements for a specific product
     */
    public function getProductMovements(int $productId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['product_id'] = $productId;
        return $this->getMovements($filters, $perPage);
    }

    /**
     * Get movements for a specific warehouse
     */
    public function getWarehouseMovements(int $warehouseId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['warehouse_id'] = $warehouseId;
        return $this->getMovements($filters, $perPage);
    }

    /**
     * Create a stock movement record
     */
    public function createMovement(array $data): StockMovement
    {
        Log::info('Creating stock movement', [
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'movement_type' => $data['movement_type'],
            'quantity' => $data['quantity'],
        ]);

        try {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();
            $data['total_cost'] = abs($data['quantity']) * ($data['unit_cost'] ?? 0);
            $data['movement_date'] = $data['movement_date'] ?? now();

            $movement = StockMovement::create($data);

            Log::info('Stock movement created', [
                'movement_id' => $movement->id,
            ]);

            return $movement;

        } catch (Exception $e) {
            Log::error('Failed to create stock movement', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get movement summary by type
     */
    public function getMovementSummary(array $filters = []): array
    {
        $query = StockMovement::query();

        // Apply filters
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('movement_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('movement_date', '<=', $filters['end_date']);
        }

        $summary = $query->selectRaw("
            movement_type,
            COUNT(*) as count,
            SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_out,
            SUM(total_cost) as total_cost
        ")
        ->groupBy('movement_type')
        ->get();

        $totals = [
            'total_movements' => $summary->sum('count'),
            'total_inbound' => $summary->sum('total_in'),
            'total_outbound' => $summary->sum('total_out'),
            'total_cost' => $summary->sum('total_cost'),
        ];

        return [
            'by_type' => $summary,
            'totals' => $totals,
        ];
    }

    /**
     * Get daily movement report
     */
    public function getDailyReport(Carbon $date, ?int $warehouseId = null): array
    {
        $query = StockMovement::with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->whereDate('movement_date', $date);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $movements = $query->get();

        return [
            'date' => $date->toDateString(),
            'warehouse_id' => $warehouseId,
            'total_movements' => $movements->count(),
            'total_inbound' => $movements->where('quantity', '>', 0)->sum('quantity'),
            'total_outbound' => $movements->where('quantity', '<', 0)->sum(fn ($m) => abs($m->quantity)),
            'total_cost' => $movements->sum('total_cost'),
            'movements' => $movements,
        ];
    }

    /**
     * Get movement history for audit
     */
    public function getAuditTrail(int $productId, int $warehouseId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = StockMovement::with(['creator:id,first_name,last_name'])
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($startDate) {
            $query->where('movement_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('movement_date', '<=', $endDate);
        }

        return $query->orderBy('movement_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Recalculate stock quantities from movements
     * Used for reconciliation and fixing discrepancies
     */
    public function recalculateStock(int $productId, int $warehouseId, ?string $lotNumber = null): float
    {
        $query = StockMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($lotNumber) {
            $query->where('lot_number', $lotNumber);
        }

        $calculatedQuantity = $query->sum('quantity');

        // Find the stock record
        $stock = Stock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('lot_number', $lotNumber)
            ->first();

        if ($stock && $stock->quantity_on_hand != $calculatedQuantity) {
            Log::warning('Stock discrepancy detected', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'current_quantity' => $stock->quantity_on_hand,
                'calculated_quantity' => $calculatedQuantity,
                'difference' => $calculatedQuantity - $stock->quantity_on_hand,
            ]);
        }

        return $calculatedQuantity;
    }

    /**
     * Get movement types for dropdown
     */
    public function getMovementTypes(): array
    {
        return [
            StockMovement::TYPE_RECEIPT => 'Receipt',
            StockMovement::TYPE_ISSUE => 'Issue',
            StockMovement::TYPE_TRANSFER => 'Transfer',
            StockMovement::TYPE_ADJUSTMENT => 'Adjustment',
            StockMovement::TYPE_PRODUCTION_CONSUME => 'Production Consume',
            StockMovement::TYPE_PRODUCTION_OUTPUT => 'Production Output',
            StockMovement::TYPE_RETURN => 'Return',
            StockMovement::TYPE_SCRAP => 'Scrap',
        ];
    }

    /**
     * Get transaction types for dropdown
     */
    public function getTransactionTypes(): array
    {
        return [
            StockMovement::TRANS_PURCHASE_ORDER => 'Purchase Order',
            StockMovement::TRANS_SALES_ORDER => 'Sales Order',
            StockMovement::TRANS_PRODUCTION_ORDER => 'Production Order',
            StockMovement::TRANS_TRANSFER_ORDER => 'Transfer Order',
            StockMovement::TRANS_ADJUSTMENT => 'Adjustment',
            StockMovement::TRANS_INITIAL_STOCK => 'Initial Stock',
            StockMovement::TRANS_RETURN => 'Return',
            StockMovement::TRANS_SCRAP => 'Scrap',
        ];
    }
}
