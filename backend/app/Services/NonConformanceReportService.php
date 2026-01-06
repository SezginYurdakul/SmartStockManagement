<?php

namespace App\Services;

use App\Enums\NcrDisposition;
use App\Enums\NcrSeverity;
use App\Enums\NcrStatus;
use App\Exceptions\BusinessException;
use App\Models\NonConformanceReport;
use App\Models\ReceivingInspection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class NonConformanceReportService
{
    /**
     * Get paginated NCRs with filters
     */
    public function getNcrs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = NonConformanceReport::query()
            ->with(['product', 'supplier', 'reporter', 'reviewer']);

        // Search
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ncr_number', 'ilike', "%{$filters['search']}%")
                  ->orWhere('title', 'ilike', "%{$filters['search']}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Severity filter
        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }

        // Source type filter
        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        // Supplier filter
        if (!empty($filters['supplier_id'])) {
            $query->forSupplier($filters['supplier_id']);
        }

        // Open only
        if (!empty($filters['open'])) {
            $query->open();
        }

        // Critical/Major only
        if (!empty($filters['critical_only'])) {
            $query->criticalOrMajor();
        }

        // Date range
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        return $query->latest('reported_at')->paginate($perPage);
    }

    /**
     * Get single NCR
     */
    public function getNcr(NonConformanceReport $ncr): NonConformanceReport
    {
        return $ncr->load([
            'receivingInspection.goodsReceivedNote',
            'product',
            'supplier',
            'reporter',
            'reviewer',
            'dispositionApprover',
            'closer',
        ]);
    }

    /**
     * Create NCR from receiving inspection
     */
    public function createFromInspection(ReceivingInspection $inspection, array $data): NonConformanceReport
    {
        Log::info('Creating NCR from inspection', [
            'inspection_id' => $inspection->id,
            'inspection_number' => $inspection->inspection_number,
        ]);

        $data['source_type'] = NonConformanceReport::SOURCE_RECEIVING;
        $data['receiving_inspection_id'] = $inspection->id;
        $data['product_id'] = $inspection->product_id;
        $data['lot_number'] = $inspection->lot_number;
        $data['batch_number'] = $inspection->batch_number;
        $data['quantity_affected'] = $data['quantity_affected'] ?? $inspection->quantity_failed;

        // Get supplier from GRN
        if ($inspection->goodsReceivedNote) {
            $data['supplier_id'] = $inspection->goodsReceivedNote->supplier_id;
        }

        return $this->create($data);
    }

    /**
     * Create new NCR
     */
    public function create(array $data): NonConformanceReport
    {
        Log::info('Creating new NCR', [
            'title' => $data['title'],
            'source_type' => $data['source_type'] ?? 'internal',
        ]);

        DB::beginTransaction();

        try {
            $companyId = Auth::user()->company_id;

            // Generate NCR number if not provided
            if (empty($data['ncr_number'])) {
                $data['ncr_number'] = $this->generateNcrNumber();
            }

            $ncr = NonConformanceReport::create([
                'company_id' => $companyId,
                'source_type' => $data['source_type'] ?? NonConformanceReport::SOURCE_INTERNAL,
                'receiving_inspection_id' => $data['receiving_inspection_id'] ?? null,
                'ncr_number' => $data['ncr_number'],
                'title' => $data['title'],
                'description' => $data['description'],
                'product_id' => $data['product_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'quantity_affected' => $data['quantity_affected'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'] ?? null,
                'severity' => $data['severity'] ?? NcrSeverity::MINOR->value,
                'priority' => $data['priority'] ?? 'medium',
                'defect_type' => $data['defect_type'] ?? 'other',
                'root_cause' => $data['root_cause'] ?? null,
                'disposition' => NcrDisposition::PENDING->value,
                'status' => NcrStatus::OPEN->value,
                'attachments' => $data['attachments'] ?? null,
                'reported_by' => Auth::id(),
                'reported_at' => now(),
            ]);

            DB::commit();

            Log::info('NCR created successfully', [
                'ncr_id' => $ncr->id,
                'ncr_number' => $ncr->ncr_number,
            ]);

            return $ncr->fresh(['product', 'supplier', 'reporter']);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create NCR', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update NCR
     */
    public function update(NonConformanceReport $ncr, array $data): NonConformanceReport
    {
        if (!$ncr->canBeEdited()) {
            throw new BusinessException('NCR cannot be edited in current status.');
        }

        Log::info('Updating NCR', [
            'ncr_id' => $ncr->id,
            'changes' => array_keys($data),
        ]);

        $ncr->update([
            'title' => $data['title'] ?? $ncr->title,
            'description' => $data['description'] ?? $ncr->description,
            'product_id' => $data['product_id'] ?? $ncr->product_id,
            'supplier_id' => $data['supplier_id'] ?? $ncr->supplier_id,
            'lot_number' => $data['lot_number'] ?? $ncr->lot_number,
            'batch_number' => $data['batch_number'] ?? $ncr->batch_number,
            'quantity_affected' => $data['quantity_affected'] ?? $ncr->quantity_affected,
            'unit_of_measure' => $data['unit_of_measure'] ?? $ncr->unit_of_measure,
            'severity' => $data['severity'] ?? $ncr->severity,
            'priority' => $data['priority'] ?? $ncr->priority,
            'defect_type' => $data['defect_type'] ?? $ncr->defect_type,
            'root_cause' => $data['root_cause'] ?? $ncr->root_cause,
            'attachments' => $data['attachments'] ?? $ncr->attachments,
        ]);

        return $ncr->fresh();
    }

    /**
     * Submit for review
     */
    public function submitForReview(NonConformanceReport $ncr): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::UNDER_REVIEW;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('Only open NCRs can be submitted for review.');
        }

        Log::info('Submitting NCR for review', [
            'ncr_id' => $ncr->id,
            'ncr_number' => $ncr->ncr_number,
        ]);

        $ncr->update([
            'status' => $targetStatus->value,
        ]);

        return $ncr->fresh();
    }

    /**
     * Complete review
     */
    public function completeReview(NonConformanceReport $ncr, array $data): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::PENDING_DISPOSITION;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('NCR is not under review.');
        }

        Log::info('Completing NCR review', [
            'ncr_id' => $ncr->id,
            'ncr_number' => $ncr->ncr_number,
        ]);

        $ncr->update([
            'status' => $targetStatus->value,
            'root_cause' => $data['root_cause'] ?? $ncr->root_cause,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $ncr->fresh();
    }

    /**
     * Set disposition
     */
    public function setDisposition(NonConformanceReport $ncr, array $data): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::DISPOSITION_APPROVED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('NCR is not ready for disposition.');
        }

        Log::info('Setting NCR disposition', [
            'ncr_id' => $ncr->id,
            'disposition' => $data['disposition'],
        ]);

        $ncr->update([
            'status' => $targetStatus->value,
            'disposition' => $data['disposition'],
            'disposition_reason' => $data['disposition_reason'] ?? null,
            'cost_impact' => $data['cost_impact'] ?? null,
            'cost_currency' => $data['cost_currency'] ?? null,
            'disposition_by' => Auth::id(),
            'disposition_at' => now(),
        ]);

        return $ncr->fresh();
    }

    /**
     * Start working on NCR (after disposition approved)
     */
    public function startProgress(NonConformanceReport $ncr): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::IN_PROGRESS;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('NCR disposition must be approved first.');
        }

        $ncr->update([
            'status' => $targetStatus->value,
        ]);

        return $ncr->fresh();
    }

    /**
     * Close NCR
     */
    public function close(NonConformanceReport $ncr, array $data): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::CLOSED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('NCR cannot be closed from current status.');
        }

        Log::info('Closing NCR', [
            'ncr_id' => $ncr->id,
            'ncr_number' => $ncr->ncr_number,
        ]);

        $ncr->update([
            'status' => $targetStatus->value,
            'closure_notes' => $data['closure_notes'] ?? null,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return $ncr->fresh();
    }

    /**
     * Cancel NCR
     */
    public function cancel(NonConformanceReport $ncr, ?string $reason = null): NonConformanceReport
    {
        $currentStatus = $ncr->status_enum;
        $targetStatus = NcrStatus::CANCELLED;

        if (!$currentStatus || !in_array($targetStatus, $currentStatus->allowedTransitions())) {
            throw new BusinessException('NCR cannot be cancelled from current status.');
        }

        Log::info('Cancelling NCR', [
            'ncr_id' => $ncr->id,
            'ncr_number' => $ncr->ncr_number,
            'reason' => $reason,
        ]);

        $ncr->update([
            'status' => $targetStatus->value,
            'closure_notes' => $reason ? "Cancelled: {$reason}" : null,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        return $ncr->fresh();
    }

    /**
     * Delete NCR (soft delete)
     */
    public function delete(NonConformanceReport $ncr): bool
    {
        $currentStatus = $ncr->status_enum;

        if (!$currentStatus || !in_array($currentStatus, [NcrStatus::OPEN, NcrStatus::CANCELLED])) {
            throw new BusinessException('Only open or cancelled NCRs can be deleted.');
        }

        Log::info('Deleting NCR', [
            'ncr_id' => $ncr->id,
            'ncr_number' => $ncr->ncr_number,
        ]);

        return $ncr->delete();
    }

    /**
     * Generate NCR number
     */
    public function generateNcrNumber(): string
    {
        $companyId = Auth::user()->company_id;
        $year = now()->format('Y');
        $prefix = "NCR-{$year}-";

        $lastNcr = NonConformanceReport::withTrashed()
            ->where('company_id', $companyId)
            ->where('ncr_number', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(ncr_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastNcr && preg_match('/(\d+)$/', $lastNcr->ncr_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get NCR statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = NonConformanceReport::query();

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->forSupplier($filters['supplier_id']);
        }

        $openNcrs = $query->clone()->open();

        return [
            'total_ncrs' => $query->clone()->count(),
            'open_ncrs' => $openNcrs->clone()->count(),
            'closed_ncrs' => $query->clone()->closed()->count(),
            'critical_open' => $openNcrs->clone()->bySeverity(NcrSeverity::CRITICAL->value)->count(),
            'major_open' => $openNcrs->clone()->bySeverity(NcrSeverity::MAJOR->value)->count(),
            'minor_open' => $openNcrs->clone()->bySeverity(NcrSeverity::MINOR->value)->count(),
            'avg_days_open' => $query->clone()->open()->avg(DB::raw('EXTRACT(DAY FROM NOW() - reported_at)')),
            'total_cost_impact' => $query->clone()->whereNotNull('cost_impact')->sum('cost_impact'),
            'by_source' => [
                'receiving' => $query->clone()->where('source_type', NonConformanceReport::SOURCE_RECEIVING)->count(),
                'production' => $query->clone()->where('source_type', NonConformanceReport::SOURCE_PRODUCTION)->count(),
                'internal' => $query->clone()->where('source_type', NonConformanceReport::SOURCE_INTERNAL)->count(),
                'customer' => $query->clone()->where('source_type', NonConformanceReport::SOURCE_CUSTOMER)->count(),
            ],
        ];
    }

    /**
     * Get supplier NCR summary
     */
    public function getSupplierSummary(int $supplierId, array $filters = []): array
    {
        $query = NonConformanceReport::forSupplier($supplierId);

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        return [
            'total_ncrs' => $query->clone()->count(),
            'open_ncrs' => $query->clone()->open()->count(),
            'by_severity' => [
                'critical' => $query->clone()->bySeverity(NcrSeverity::CRITICAL->value)->count(),
                'major' => $query->clone()->bySeverity(NcrSeverity::MAJOR->value)->count(),
                'minor' => $query->clone()->bySeverity(NcrSeverity::MINOR->value)->count(),
            ],
            'total_cost_impact' => $query->clone()->whereNotNull('cost_impact')->sum('cost_impact'),
        ];
    }

    /**
     * Get statuses for dropdown
     */
    public function getStatuses(): array
    {
        $statuses = [];
        foreach (\App\Enums\NcrStatus::cases() as $status) {
            $statuses[$status->value] = $status->fallbackLabel();
        }
        return $statuses;
    }

    /**
     * Get severities for dropdown
     */
    public function getSeverities(): array
    {
        $severities = [];
        foreach (\App\Enums\NcrSeverity::cases() as $severity) {
            $severities[$severity->value] = $severity->fallbackLabel();
        }
        return $severities;
    }

    /**
     * Get defect types for dropdown
     */
    public function getDefectTypes(): array
    {
        return NonConformanceReport::DEFECT_TYPES;
    }

    /**
     * Get dispositions for dropdown
     */
    public function getDispositions(): array
    {
        $dispositions = [];
        foreach (\App\Enums\NcrDisposition::cases() as $disposition) {
            $dispositions[$disposition->value] = $disposition->fallbackLabel();
        }
        return $dispositions;
    }
}
