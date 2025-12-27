<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\ReceivingInspection;
use App\Models\AcceptanceRule;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ReceivingInspectionService
{
    protected AcceptanceRuleService $acceptanceRuleService;
    protected ?QualityHoldService $qualityHoldService;

    public function __construct(
        AcceptanceRuleService $acceptanceRuleService,
        ?QualityHoldService $qualityHoldService = null
    ) {
        $this->acceptanceRuleService = $acceptanceRuleService;
        $this->qualityHoldService = $qualityHoldService;
    }

    /**
     * Get paginated inspections with filters
     */
    public function getInspections(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ReceivingInspection::query()
            ->with(['goodsReceivedNote', 'product', 'inspector', 'acceptanceRule']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('inspection_number', 'ilike', "%{$filters['search']}%")
                  ->orWhere('lot_number', 'ilike', "%{$filters['search']}%");
            });
        }

        // Result filter
        if (!empty($filters['result'])) {
            $query->where('result', $filters['result']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        // GRN filter
        if (!empty($filters['grn_id'])) {
            $query->where('goods_received_note_id', $filters['grn_id']);
        }

        // Date range
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        // Pending only
        if (!empty($filters['pending'])) {
            $query->pending();
        }

        // Failed only
        if (!empty($filters['failed'])) {
            $query->failed();
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Get single inspection
     */
    public function getInspection(ReceivingInspection $inspection): ReceivingInspection
    {
        return $inspection->load([
            'goodsReceivedNote.supplier',
            'grnItem',
            'product',
            'acceptanceRule',
            'inspector',
            'approver',
            'nonConformanceReports',
        ]);
    }

    /**
     * Get inspections for a GRN
     */
    public function getInspectionsForGrn(GoodsReceivedNote $grn): Collection
    {
        return ReceivingInspection::where('goods_received_note_id', $grn->id)
            ->with(['product', 'acceptanceRule', 'inspector'])
            ->get();
    }

    /**
     * Create inspections for all items in a GRN
     */
    public function createInspectionsForGrn(GoodsReceivedNote $grn): Collection
    {
        Log::info('Creating inspections for GRN', [
            'grn_id' => $grn->id,
            'grn_number' => $grn->grn_number,
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;
            $supplierId = $grn->supplier_id;
            $inspections = collect();

            foreach ($grn->items as $item) {
                // Find applicable acceptance rule
                $rule = $this->acceptanceRuleService->findApplicableRule(
                    $item->product_id,
                    $supplierId
                );

                // Calculate sample size
                $sampleSize = $rule
                    ? $rule->calculateSampleSize((int) $item->quantity_received)
                    : $item->quantity_received;

                $inspection = ReceivingInspection::create([
                    'company_id' => $companyId,
                    'goods_received_note_id' => $grn->id,
                    'grn_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'acceptance_rule_id' => $rule?->id,
                    'inspection_number' => $this->generateInspectionNumber(),
                    'lot_number' => $item->lot_number,
                    'batch_number' => $item->batch_number,
                    'quantity_received' => $item->quantity_received,
                    'quantity_inspected' => $sampleSize,
                    'result' => ReceivingInspection::RESULT_PENDING,
                    'disposition' => ReceivingInspection::DISPOSITION_PENDING,
                ]);

                $inspections->push($inspection);
            }

            DB::commit();

            Log::info('Inspections created for GRN', [
                'grn_id' => $grn->id,
                'inspection_count' => $inspections->count(),
            ]);

            return $inspections;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create inspections for GRN', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Record inspection result
     */
    public function recordResult(ReceivingInspection $inspection, array $data): ReceivingInspection
    {
        if ($inspection->isComplete()) {
            throw new BusinessException('Inspection has already been completed.');
        }

        Log::info('Recording inspection result', [
            'inspection_id' => $inspection->id,
            'inspection_number' => $inspection->inspection_number,
        ]);

        DB::beginTransaction();

        try {
            $quantityPassed = $data['quantity_passed'] ?? 0;
            $quantityFailed = $data['quantity_failed'] ?? 0;
            $quantityOnHold = $data['quantity_on_hold'] ?? 0;

            // Validate quantities
            $totalDisposed = $quantityPassed + $quantityFailed + $quantityOnHold;
            if ($totalDisposed > $inspection->quantity_inspected) {
                throw new BusinessException('Total disposed quantity cannot exceed inspected quantity.');
            }

            // Determine result
            $result = $this->determineResult($quantityPassed, $quantityFailed, $quantityOnHold, $inspection->quantity_inspected);

            $inspection->update([
                'quantity_passed' => $quantityPassed,
                'quantity_failed' => $quantityFailed,
                'quantity_on_hold' => $quantityOnHold,
                'result' => $result,
                'disposition' => $data['disposition'] ?? ReceivingInspection::DISPOSITION_PENDING,
                'inspection_data' => $data['inspection_data'] ?? null,
                'failure_reason' => $data['failure_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'inspected_by' => Auth::id(),
                'inspected_at' => now(),
            ]);

            // Update GRN item quantities based on disposition
            $this->updateGrnItemQuantities($inspection);

            DB::commit();

            Log::info('Inspection result recorded', [
                'inspection_id' => $inspection->id,
                'result' => $result,
            ]);

            return $inspection->fresh(['grnItem', 'product']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to record inspection result', [
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Approve inspection (for dispositions requiring approval)
     */
    public function approve(ReceivingInspection $inspection): ReceivingInspection
    {
        if (!$inspection->isComplete()) {
            throw new BusinessException('Cannot approve incomplete inspection.');
        }

        Log::info('Approving inspection', [
            'inspection_id' => $inspection->id,
            'inspection_number' => $inspection->inspection_number,
        ]);

        $inspection->update([
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $inspection->fresh();
    }

    /**
     * Update disposition
     */
    public function updateDisposition(ReceivingInspection $inspection, string $disposition, ?string $reason = null): ReceivingInspection
    {
        Log::info('Updating inspection disposition', [
            'inspection_id' => $inspection->id,
            'disposition' => $disposition,
        ]);

        DB::beginTransaction();

        try {
            $inspection->update([
                'disposition' => $disposition,
                'notes' => $reason ? ($inspection->notes . "\nDisposition reason: " . $reason) : $inspection->notes,
            ]);

            // Update GRN item quantities based on new disposition
            $this->updateGrnItemQuantities($inspection);

            DB::commit();

            return $inspection->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Determine inspection result based on quantities
     */
    protected function determineResult(float $passed, float $failed, float $onHold, float $total): string
    {
        if ($onHold > 0) {
            return ReceivingInspection::RESULT_ON_HOLD;
        }

        if ($failed === 0 && $passed > 0) {
            return ReceivingInspection::RESULT_PASSED;
        }

        if ($passed === 0 && $failed > 0) {
            return ReceivingInspection::RESULT_FAILED;
        }

        if ($passed > 0 && $failed > 0) {
            return ReceivingInspection::RESULT_PARTIAL;
        }

        return ReceivingInspection::RESULT_PENDING;
    }

    /**
     * Update GRN item quantities based on inspection disposition
     */
    protected function updateGrnItemQuantities(ReceivingInspection $inspection): void
    {
        $grnItem = $inspection->grnItem;

        if (!$grnItem) {
            return;
        }

        // Calculate accepted/rejected based on disposition
        switch ($inspection->disposition) {
            case ReceivingInspection::DISPOSITION_ACCEPT:
            case ReceivingInspection::DISPOSITION_USE_AS_IS:
                $grnItem->update([
                    'quantity_accepted' => $inspection->quantity_passed + $inspection->quantity_on_hold,
                    'quantity_rejected' => $inspection->quantity_failed,
                ]);
                break;

            case ReceivingInspection::DISPOSITION_REJECT:
            case ReceivingInspection::DISPOSITION_RETURN:
                $grnItem->update([
                    'quantity_accepted' => 0,
                    'quantity_rejected' => $inspection->quantity_received,
                ]);
                break;

            case ReceivingInspection::DISPOSITION_REWORK:
                // Rework items are on hold until rework is complete
                $grnItem->update([
                    'quantity_accepted' => $inspection->quantity_passed,
                    'quantity_rejected' => $inspection->quantity_failed,
                ]);
                break;

            default:
                // Pending - no changes
                break;
        }

        // Update stock quality status based on disposition
        $this->updateStockQualityStatus($inspection);
    }

    /**
     * Update stock quality status based on inspection result and disposition
     */
    protected function updateStockQualityStatus(ReceivingInspection $inspection): void
    {
        // Find stock record for this inspection (by product, warehouse, and lot number)
        $grn = $inspection->goodsReceivedNote;
        if (!$grn || !$grn->warehouse_id) {
            return;
        }

        $stock = Stock::where('product_id', $inspection->product_id)
            ->where('warehouse_id', $grn->warehouse_id)
            ->where('lot_number', $inspection->lot_number)
            ->first();

        if (!$stock) {
            Log::info('No stock found for inspection quality status update', [
                'inspection_id' => $inspection->id,
                'product_id' => $inspection->product_id,
                'warehouse_id' => $grn->warehouse_id,
                'lot_number' => $inspection->lot_number,
            ]);
            return;
        }

        // Determine quality status based on disposition
        $qualityStatus = $this->getQualityStatusFromDisposition($inspection);

        if ($qualityStatus === null) {
            return; // Pending - no status change yet
        }

        // Use QualityHoldService if available, otherwise update directly
        if ($this->qualityHoldService) {
            $this->qualityHoldService->placeHold(
                $stock,
                $qualityStatus,
                $this->getReasonFromInspection($inspection),
                null, // hold_until
                $this->getRestrictionsFromInspection($inspection),
                ReceivingInspection::class,
                $inspection->id
            );
        } else {
            // Direct update if QualityHoldService not injected
            $stock->placeQualityHold(
                $qualityStatus,
                $this->getReasonFromInspection($inspection),
                null, // hold_until
                $this->getRestrictionsFromInspection($inspection),
                Auth::id(),
                ReceivingInspection::class,
                $inspection->id
            );
        }

        Log::info('Stock quality status updated from inspection', [
            'stock_id' => $stock->id,
            'inspection_id' => $inspection->id,
            'quality_status' => $qualityStatus,
        ]);
    }

    /**
     * Get quality status from inspection disposition
     */
    protected function getQualityStatusFromDisposition(ReceivingInspection $inspection): ?string
    {
        return match ($inspection->disposition) {
            ReceivingInspection::DISPOSITION_ACCEPT => Stock::QUALITY_AVAILABLE,
            ReceivingInspection::DISPOSITION_USE_AS_IS => Stock::QUALITY_CONDITIONAL,
            ReceivingInspection::DISPOSITION_REJECT,
            ReceivingInspection::DISPOSITION_RETURN => Stock::QUALITY_REJECTED,
            ReceivingInspection::DISPOSITION_REWORK => Stock::QUALITY_ON_HOLD,
            ReceivingInspection::DISPOSITION_QUARANTINE => Stock::QUALITY_QUARANTINE,
            default => null, // Pending - no change
        };
    }

    /**
     * Get reason text from inspection
     */
    protected function getReasonFromInspection(ReceivingInspection $inspection): ?string
    {
        $parts = [];

        if ($inspection->failure_reason) {
            $parts[] = $inspection->failure_reason;
        }

        if ($inspection->notes) {
            $parts[] = $inspection->notes;
        }

        if (empty($parts)) {
            $parts[] = "Inspection #{$inspection->inspection_number} - {$inspection->disposition}";
        }

        return implode('; ', $parts);
    }

    /**
     * Get quality restrictions from inspection (for conditional acceptance)
     */
    protected function getRestrictionsFromInspection(ReceivingInspection $inspection): ?array
    {
        if ($inspection->disposition !== ReceivingInspection::DISPOSITION_USE_AS_IS) {
            return null;
        }

        // For conditional use, allow production but block sale
        return [
            'allowed_operations' => [Stock::OPERATION_PRODUCTION, Stock::OPERATION_TRANSFER, Stock::OPERATION_ADJUSTMENT],
            'blocked_operations' => [Stock::OPERATION_SALE, Stock::OPERATION_BUNDLE],
            'reason' => 'Accepted with conditions from inspection',
            'inspection_id' => $inspection->id,
        ];
    }

    /**
     * Process inspection to transfer stock to quarantine/rejection warehouse
     */
    public function transferToQcZone(ReceivingInspection $inspection, int $targetWarehouseId): ReceivingInspection
    {
        $grn = $inspection->goodsReceivedNote;
        if (!$grn || !$grn->warehouse_id) {
            throw new BusinessException('GRN warehouse not found');
        }

        $targetWarehouse = Warehouse::find($targetWarehouseId);
        if (!$targetWarehouse) {
            throw new BusinessException('Target warehouse not found');
        }

        if (!$targetWarehouse->isQcZone()) {
            throw new BusinessException('Target warehouse must be a QC zone (quarantine or rejection)');
        }

        $stock = Stock::where('product_id', $inspection->product_id)
            ->where('warehouse_id', $grn->warehouse_id)
            ->where('lot_number', $inspection->lot_number)
            ->first();

        if (!$stock) {
            throw new BusinessException('Stock not found for inspection');
        }

        DB::beginTransaction();

        try {
            if ($this->qualityHoldService) {
                if ($targetWarehouse->is_quarantine_zone) {
                    $this->qualityHoldService->transferToQuarantine(
                        $stock,
                        $targetWarehouse,
                        $inspection->failure_reason ?? 'Inspection failed',
                        ReceivingInspection::class,
                        $inspection->id
                    );
                } else {
                    $this->qualityHoldService->transferToRejection(
                        $stock,
                        $targetWarehouse,
                        $inspection->failure_reason ?? 'Rejected by inspection',
                        ReceivingInspection::class,
                        $inspection->id
                    );
                }
            }

            DB::commit();

            return $inspection->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate inspection number
     */
    public function generateInspectionNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $prefix = "INS-{$year}-";

        $lastInspection = ReceivingInspection::where('company_id', $companyId)
            ->where('inspection_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(inspection_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastInspection && preg_match('/(\d+)$/', $lastInspection->inspection_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get inspection statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = ReceivingInspection::query();

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        $totalInspected = $query->clone()->sum('quantity_inspected');
        $totalPassed = $query->clone()->sum('quantity_passed');
        $totalFailed = $query->clone()->sum('quantity_failed');

        return [
            'total_inspections' => $query->clone()->count(),
            'pending_inspections' => $query->clone()->pending()->count(),
            'passed_inspections' => $query->clone()->where('result', ReceivingInspection::RESULT_PASSED)->count(),
            'failed_inspections' => $query->clone()->where('result', ReceivingInspection::RESULT_FAILED)->count(),
            'partial_inspections' => $query->clone()->where('result', ReceivingInspection::RESULT_PARTIAL)->count(),
            'total_quantity_inspected' => $totalInspected,
            'total_quantity_passed' => $totalPassed,
            'total_quantity_failed' => $totalFailed,
            'pass_rate' => $totalInspected > 0 ? round(($totalPassed / $totalInspected) * 100, 2) : 0,
        ];
    }

    /**
     * Get results for dropdown
     */
    public function getResults(): array
    {
        return ReceivingInspection::RESULTS;
    }

    /**
     * Get dispositions for dropdown
     */
    public function getDispositions(): array
    {
        return ReceivingInspection::DISPOSITIONS;
    }
}
