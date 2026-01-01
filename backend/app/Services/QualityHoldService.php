<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Exceptions\QualityHoldException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QualityHoldService
{
    /**
     * Validate if operation is allowed on stock
     *
     * @throws QualityHoldException
     */
    public function validateOperation(Stock $stock, string $operation): void
    {
        if (!$stock->isOperationAllowed($operation)) {
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
     * Validate multiple stocks for an operation
     *
     * @param Collection|array $stocks
     * @throws QualityHoldException
     */
    public function validateOperationForStocks($stocks, string $operation): void
    {
        $blockedStocks = [];

        foreach ($stocks as $stock) {
            if (!$stock->isOperationAllowed($operation)) {
                $blockedStocks[] = [
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'lot_number' => $stock->lot_number,
                    'quality_status' => $stock->quality_status,
                    'hold_reason' => $stock->hold_reason,
                ];
            }
        }

        if (!empty($blockedStocks)) {
            throw new QualityHoldException(
                "Operation '{$operation}' is blocked for " . count($blockedStocks) . " stock item(s) due to quality hold",
                [
                    'operation' => $operation,
                    'blocked_stocks' => $blockedStocks,
                ]
            );
        }
    }

    /**
     * Place quality hold on stock
     */
    public function placeHold(
        Stock $stock,
        string $status,
        ?string $reason = null,
        ?\DateTimeInterface $holdUntil = null,
        ?array $restrictions = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Stock {
        return DB::transaction(function () use ($stock, $status, $reason, $holdUntil, $restrictions, $referenceType, $referenceId) {
            $previousStatus = $stock->quality_status;

            $stock->placeQualityHold(
                $status,
                $reason,
                $holdUntil,
                $restrictions,
                Auth::id(),
                $referenceType,
                $referenceId
            );

            // Create stock movement record for quality status change
            $this->createQualityMovement($stock, $previousStatus, $status, $referenceType, $referenceId);

            return $stock->fresh();
        });
    }

    /**
     * Release quality hold from stock
     */
    public function releaseHold(Stock $stock): Stock
    {
        return DB::transaction(function () use ($stock) {
            $previousStatus = $stock->quality_status;

            $stock->releaseQualityHold();

            // Create stock movement record for quality status change
            $this->createQualityMovement($stock, $previousStatus, Stock::QUALITY_AVAILABLE);

            return $stock->fresh();
        });
    }

    /**
     * Set conditional status on stock
     */
    public function setConditional(
        Stock $stock,
        array $restrictions,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Stock {
        return DB::transaction(function () use ($stock, $restrictions, $reason, $referenceType, $referenceId) {
            $previousStatus = $stock->quality_status;

            $stock->update([
                'quality_status' => Stock::QUALITY_CONDITIONAL,
                'hold_reason' => $reason,
                'quality_restrictions' => $restrictions,
                'quality_hold_by' => Auth::id(),
                'quality_hold_at' => now(),
                'quality_reference_type' => $referenceType,
                'quality_reference_id' => $referenceId,
            ]);

            // Create stock movement record
            $this->createQualityMovement($stock, $previousStatus, Stock::QUALITY_CONDITIONAL, $referenceType, $referenceId);

            return $stock->fresh();
        });
    }

    /**
     * Place hold on multiple stocks (batch operation)
     */
    public function placeHoldOnMultiple(
        Collection $stocks,
        string $status,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Collection {
        return DB::transaction(function () use ($stocks, $status, $reason, $referenceType, $referenceId) {
            $updatedStocks = collect();

            foreach ($stocks as $stock) {
                $updatedStocks->push(
                    $this->placeHold($stock, $status, $reason, null, null, $referenceType, $referenceId)
                );
            }

            return $updatedStocks;
        });
    }

    /**
     * Transfer stock to quarantine warehouse
     */
    public function transferToQuarantine(
        Stock $stock,
        Warehouse $quarantineWarehouse,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Stock {
        if (!$quarantineWarehouse->is_quarantine_zone) {
            throw new QualityHoldException(
                "Warehouse '{$quarantineWarehouse->name}' is not a quarantine zone",
                ['warehouse_id' => $quarantineWarehouse->id]
            );
        }

        return DB::transaction(function () use ($stock, $quarantineWarehouse, $reason, $referenceType, $referenceId) {
            $previousWarehouseId = $stock->warehouse_id;
            $previousStatus = $stock->quality_status;

            // Update stock location and quality status
            $stock->update([
                'warehouse_id' => $quarantineWarehouse->id,
                'quality_status' => Stock::QUALITY_QUARANTINE,
                'hold_reason' => $reason,
                'quality_hold_by' => Auth::id(),
                'quality_hold_at' => now(),
                'quality_reference_type' => $referenceType,
                'quality_reference_id' => $referenceId,
            ]);

            // Create transfer movement
            StockMovement::create([
                'company_id' => $stock->company_id,
                'product_id' => $stock->product_id,
                'warehouse_id' => $quarantineWarehouse->id,
                'from_warehouse_id' => $previousWarehouseId,
                'movement_type' => 'transfer_in',
                'quantity' => $stock->quantity_on_hand,
                'unit_cost' => $stock->unit_cost,
                'lot_number' => $stock->lot_number,
                'serial_number' => $stock->serial_number,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quality_status_from' => $previousStatus,
                'quality_status_to' => Stock::QUALITY_QUARANTINE,
                'notes' => "Transferred to quarantine: {$reason}",
                'created_by' => Auth::id(),
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Transfer stock to rejection warehouse
     */
    public function transferToRejection(
        Stock $stock,
        Warehouse $rejectionWarehouse,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Stock {
        if (!$rejectionWarehouse->is_rejection_zone) {
            throw new QualityHoldException(
                "Warehouse '{$rejectionWarehouse->name}' is not a rejection zone",
                ['warehouse_id' => $rejectionWarehouse->id]
            );
        }

        return DB::transaction(function () use ($stock, $rejectionWarehouse, $reason, $referenceType, $referenceId) {
            $previousWarehouseId = $stock->warehouse_id;
            $previousStatus = $stock->quality_status;

            // Update stock location and quality status
            $stock->update([
                'warehouse_id' => $rejectionWarehouse->id,
                'quality_status' => Stock::QUALITY_REJECTED,
                'hold_reason' => $reason,
                'quality_hold_by' => Auth::id(),
                'quality_hold_at' => now(),
                'quality_reference_type' => $referenceType,
                'quality_reference_id' => $referenceId,
            ]);

            // Create transfer movement
            StockMovement::create([
                'company_id' => $stock->company_id,
                'product_id' => $stock->product_id,
                'warehouse_id' => $rejectionWarehouse->id,
                'from_warehouse_id' => $previousWarehouseId,
                'movement_type' => 'transfer_in',
                'quantity' => $stock->quantity_on_hand,
                'unit_cost' => $stock->unit_cost,
                'lot_number' => $stock->lot_number,
                'serial_number' => $stock->serial_number,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quality_status_from' => $previousStatus,
                'quality_status_to' => Stock::QUALITY_REJECTED,
                'notes' => "Transferred to rejection: {$reason}",
                'created_by' => Auth::id(),
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Release stock from quarantine/rejection back to main warehouse
     */
    public function releaseFromQcZone(
        Stock $stock,
        Warehouse $destinationWarehouse,
        string $newQualityStatus = Stock::QUALITY_AVAILABLE
    ): Stock {
        $currentWarehouse = $stock->warehouse;

        if (!$currentWarehouse->isQcZone()) {
            throw new QualityHoldException(
                "Stock is not in a QC zone (quarantine or rejection)",
                ['current_warehouse_id' => $currentWarehouse->id]
            );
        }

        if ($destinationWarehouse->isQcZone()) {
            throw new QualityHoldException(
                "Destination warehouse cannot be a QC zone",
                ['destination_warehouse_id' => $destinationWarehouse->id]
            );
        }

        return DB::transaction(function () use ($stock, $destinationWarehouse, $newQualityStatus, $currentWarehouse) {
            $previousStatus = $stock->quality_status;

            // Update stock
            $stock->update([
                'warehouse_id' => $destinationWarehouse->id,
                'quality_status' => $newQualityStatus,
                'hold_reason' => null,
                'hold_until' => null,
                'quality_restrictions' => $newQualityStatus === Stock::QUALITY_CONDITIONAL
                    ? $stock->quality_restrictions
                    : null,
                'quality_hold_by' => null,
                'quality_hold_at' => null,
            ]);

            // Create transfer movement
            StockMovement::create([
                'company_id' => $stock->company_id,
                'product_id' => $stock->product_id,
                'warehouse_id' => $destinationWarehouse->id,
                'from_warehouse_id' => $currentWarehouse->id,
                'movement_type' => 'transfer_in',
                'quantity' => $stock->quantity_on_hand,
                'unit_cost' => $stock->unit_cost,
                'lot_number' => $stock->lot_number,
                'serial_number' => $stock->serial_number,
                'quality_status_from' => $previousStatus,
                'quality_status_to' => $newQualityStatus,
                'notes' => "Released from QC zone",
                'created_by' => Auth::id(),
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Get stocks on quality hold
     */
    public function getStocksOnHold(int $companyId, array $filters = [])
    {
        $query = Stock::where('company_id', $companyId)
            ->onQualityHold()
            ->with(['product', 'warehouse', 'qualityHoldBy']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['quality_status'])) {
            $query->where('quality_status', $filters['quality_status']);
        }

        return $query->orderBy('quality_hold_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get expired holds (hold_until has passed)
     */
    public function getExpiredHolds(int $companyId)
    {
        return Stock::where('company_id', $companyId)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<', now())
            ->whereNotIn('quality_status', [Stock::QUALITY_AVAILABLE])
            ->with(['product', 'warehouse'])
            ->get();
    }

    /**
     * Auto-release expired holds
     */
    public function releaseExpiredHolds(int $companyId): int
    {
        $expiredStocks = $this->getExpiredHolds($companyId);
        $released = 0;

        foreach ($expiredStocks as $stock) {
            $this->releaseHold($stock);
            $released++;
        }

        return $released;
    }

    /**
     * Create quality status change movement record
     */
    protected function createQualityMovement(
        Stock $stock,
        ?string $fromStatus,
        string $toStatus,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        StockMovement::create([
            'company_id' => $stock->company_id,
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'movement_type' => 'adjustment',
            'quantity' => 0, // No quantity change, just status change
            'unit_cost' => $stock->unit_cost,
            'lot_number' => $stock->lot_number,
            'serial_number' => $stock->serial_number,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quality_status_from' => $fromStatus,
            'quality_status_to' => $toStatus,
            'notes' => "Quality status changed from {$fromStatus} to {$toStatus}",
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Get quality hold statistics
     */
    public function getStatistics(int $companyId): array
    {
        $stocks = Stock::where('company_id', $companyId)
            ->selectRaw('quality_status, COUNT(*) as count, SUM(quantity_on_hand) as total_quantity')
            ->groupBy('quality_status')
            ->get()
            ->keyBy('quality_status');

        return [
            'by_status' => $stocks->map(fn ($s) => [
                'count' => $s->count,
                'total_quantity' => $s->total_quantity,
            ])->toArray(),
            'on_hold_count' => Stock::where('company_id', $companyId)->onQualityHold()->count(),
            'rejected_count' => Stock::where('company_id', $companyId)->qualityRejected()->count(),
            'expired_holds_count' => $this->getExpiredHolds($companyId)->count(),
        ];
    }
}
