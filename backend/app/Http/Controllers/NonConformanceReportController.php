<?php

namespace App\Http\Controllers;

use App\Models\NonConformanceReport;
use App\Models\ReceivingInspection;
use App\Services\NonConformanceReportService;
use App\Http\Resources\NonConformanceReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class NonConformanceReportController extends Controller
{
    public function __construct(
        protected NonConformanceReportService $ncrService
    ) {}

    /**
     * Display a listing of NCRs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'severity',
            'source_type',
            'product_id',
            'supplier_id',
            'open',
            'critical_only',
            'from_date',
            'to_date',
        ]);
        $perPage = $request->get('per_page', 15);

        $ncrs = $this->ncrService->getNcrs($filters, $perPage);

        return NonConformanceReportResource::collection($ncrs);
    }

    /**
     * Store a newly created NCR
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'source_type' => ['nullable', Rule::in(array_keys(NonConformanceReport::SOURCES))],
            'product_id' => 'nullable|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'lot_number' => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'quantity_affected' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
            'severity' => ['required', Rule::in(array_keys(NonConformanceReport::SEVERITIES))],
            'priority' => ['nullable', Rule::in(array_keys(NonConformanceReport::PRIORITIES))],
            'defect_type' => ['required', Rule::in(array_keys(NonConformanceReport::DEFECT_TYPES))],
            'root_cause' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $ncr = $this->ncrService->create($validated);

        return response()->json([
            'message' => 'NCR created successfully',
            'data' => NonConformanceReportResource::make($ncr),
        ], 201);
    }

    /**
     * Create NCR from inspection
     */
    public function createFromInspection(Request $request, ReceivingInspection $receivingInspection): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'quantity_affected' => 'nullable|numeric|min:0',
            'severity' => ['required', Rule::in(array_keys(NonConformanceReport::SEVERITIES))],
            'priority' => ['nullable', Rule::in(array_keys(NonConformanceReport::PRIORITIES))],
            'defect_type' => ['required', Rule::in(array_keys(NonConformanceReport::DEFECT_TYPES))],
            'root_cause' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $ncr = $this->ncrService->createFromInspection($receivingInspection, $validated);

        return response()->json([
            'message' => 'NCR created from inspection successfully',
            'data' => NonConformanceReportResource::make($ncr),
        ], 201);
    }

    /**
     * Display the specified NCR
     */
    public function show(NonConformanceReport $nonConformanceReport): JsonResource
    {
        return NonConformanceReportResource::make(
            $this->ncrService->getNcr($nonConformanceReport)
        );
    }

    /**
     * Update the specified NCR
     */
    public function update(Request $request, NonConformanceReport $nonConformanceReport): JsonResource
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'product_id' => 'nullable|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'lot_number' => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'quantity_affected' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
            'severity' => ['sometimes', 'required', Rule::in(array_keys(NonConformanceReport::SEVERITIES))],
            'priority' => ['nullable', Rule::in(array_keys(NonConformanceReport::PRIORITIES))],
            'defect_type' => ['sometimes', 'required', Rule::in(array_keys(NonConformanceReport::DEFECT_TYPES))],
            'root_cause' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $ncr = $this->ncrService->update($nonConformanceReport, $validated);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'NCR updated successfully']);
    }

    /**
     * Submit NCR for review
     */
    public function submitForReview(NonConformanceReport $nonConformanceReport): JsonResource
    {
        $ncr = $this->ncrService->submitForReview($nonConformanceReport);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'NCR submitted for review']);
    }

    /**
     * Complete review
     */
    public function completeReview(Request $request, NonConformanceReport $nonConformanceReport): JsonResource
    {
        $validated = $request->validate([
            'root_cause' => 'nullable|string',
        ]);

        $ncr = $this->ncrService->completeReview($nonConformanceReport, $validated);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'Review completed']);
    }

    /**
     * Set disposition
     */
    public function setDisposition(Request $request, NonConformanceReport $nonConformanceReport): JsonResource
    {
        $validated = $request->validate([
            'disposition' => ['required', Rule::in(array_keys(NonConformanceReport::DISPOSITIONS))],
            'disposition_reason' => 'nullable|string',
            'cost_impact' => 'nullable|numeric|min:0',
            'cost_currency' => 'nullable|string|size:3',
        ]);

        $ncr = $this->ncrService->setDisposition($nonConformanceReport, $validated);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'Disposition set successfully']);
    }

    /**
     * Start progress on NCR
     */
    public function startProgress(NonConformanceReport $nonConformanceReport): JsonResource
    {
        $ncr = $this->ncrService->startProgress($nonConformanceReport);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'NCR work started']);
    }

    /**
     * Close NCR
     */
    public function close(Request $request, NonConformanceReport $nonConformanceReport): JsonResource
    {
        $validated = $request->validate([
            'closure_notes' => 'nullable|string',
        ]);

        $ncr = $this->ncrService->close($nonConformanceReport, $validated);

        return NonConformanceReportResource::make($ncr)
            ->additional(['message' => 'NCR closed successfully']);
    }

    /**
     * Cancel NCR
     */
    public function cancel(Request $request, NonConformanceReport $nonConformanceReport): JsonResponse
    {
        $reason = $request->input('reason');

        $ncr = $this->ncrService->cancel($nonConformanceReport, $reason);

        return response()->json([
            'message' => 'NCR cancelled successfully',
            'data' => NonConformanceReportResource::make($ncr),
        ]);
    }

    /**
     * Remove the specified NCR
     */
    public function destroy(NonConformanceReport $nonConformanceReport): JsonResponse
    {
        $this->ncrService->delete($nonConformanceReport);

        return response()->json([
            'message' => 'NCR deleted successfully',
        ]);
    }

    /**
     * Get NCR statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only([
            'from_date',
            'to_date',
            'supplier_id',
        ]);

        $stats = $this->ncrService->getStatistics($filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get supplier NCR summary
     */
    public function supplierSummary(Request $request, int $supplierId): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);

        $summary = $this->ncrService->getSupplierSummary($supplierId, $filters);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get statuses for dropdown
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => $this->ncrService->getStatuses(),
        ]);
    }

    /**
     * Get severities for dropdown
     */
    public function severities(): JsonResponse
    {
        return response()->json([
            'data' => $this->ncrService->getSeverities(),
        ]);
    }

    /**
     * Get defect types for dropdown
     */
    public function defectTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->ncrService->getDefectTypes(),
        ]);
    }

    /**
     * Get dispositions for dropdown
     */
    public function dispositions(): JsonResponse
    {
        return response()->json([
            'data' => $this->ncrService->getDispositions(),
        ]);
    }
}
