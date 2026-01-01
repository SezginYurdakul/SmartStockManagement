<?php

namespace App\Http\Controllers;

use App\Models\ReceivingInspection;
use App\Models\GoodsReceivedNote;
use App\Services\ReceivingInspectionService;
use App\Http\Resources\ReceivingInspectionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class ReceivingInspectionController extends Controller
{
    public function __construct(
        protected ReceivingInspectionService $inspectionService
    ) {}

    /**
     * Display a listing of inspections
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'result',
            'product_id',
            'grn_id',
            'from_date',
            'to_date',
            'pending',
            'failed',
        ]);
        $perPage = $request->get('per_page', 15);

        $inspections = $this->inspectionService->getInspections($filters, $perPage);

        return ReceivingInspectionResource::collection($inspections);
    }

    /**
     * Display the specified inspection
     */
    public function show(ReceivingInspection $receivingInspection): JsonResource
    {
        return ReceivingInspectionResource::make(
            $this->inspectionService->getInspection($receivingInspection)
        );
    }

    /**
     * Create inspections for a GRN
     */
    public function createForGrn(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $inspections = $this->inspectionService->createInspectionsForGrn($goodsReceivedNote);

        return response()->json([
            'message' => 'Inspections created successfully',
            'data' => ReceivingInspectionResource::collection($inspections),
        ], 201);
    }

    /**
     * Get inspections for a GRN
     */
    public function forGrn(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $inspections = $this->inspectionService->getInspectionsForGrn($goodsReceivedNote);

        return response()->json([
            'data' => ReceivingInspectionResource::collection($inspections),
        ]);
    }

    /**
     * Record inspection result
     */
    public function recordResult(Request $request, ReceivingInspection $receivingInspection): JsonResource
    {
        $validated = $request->validate([
            'quantity_passed' => 'required|numeric|min:0',
            'quantity_failed' => 'required|numeric|min:0',
            'quantity_on_hold' => 'nullable|numeric|min:0',
            'disposition' => ['required', Rule::in(array_keys(ReceivingInspection::DISPOSITIONS))],
            'inspection_data' => 'nullable|array',
            'failure_reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $inspection = $this->inspectionService->recordResult($receivingInspection, $validated);

        return ReceivingInspectionResource::make($inspection)
            ->additional(['message' => 'Inspection result recorded successfully']);
    }

    /**
     * Approve inspection
     */
    public function approve(ReceivingInspection $receivingInspection): JsonResource
    {
        $inspection = $this->inspectionService->approve($receivingInspection);

        return ReceivingInspectionResource::make($inspection)
            ->additional(['message' => 'Inspection approved successfully']);
    }

    /**
     * Update disposition
     */
    public function updateDisposition(Request $request, ReceivingInspection $receivingInspection): JsonResource
    {
        $validated = $request->validate([
            'disposition' => ['required', Rule::in(array_keys(ReceivingInspection::DISPOSITIONS))],
            'reason' => 'nullable|string',
        ]);

        $inspection = $this->inspectionService->updateDisposition(
            $receivingInspection,
            $validated['disposition'],
            $validated['reason'] ?? null
        );

        return ReceivingInspectionResource::make($inspection)
            ->additional(['message' => 'Disposition updated successfully']);
    }

    /**
     * Get inspection statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only([
            'from_date',
            'to_date',
            'product_id',
        ]);

        $stats = $this->inspectionService->getStatistics($filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get results for dropdown
     */
    public function results(): JsonResponse
    {
        return response()->json([
            'data' => $this->inspectionService->getResults(),
        ]);
    }

    /**
     * Get dispositions for dropdown
     */
    public function dispositions(): JsonResponse
    {
        return response()->json([
            'data' => $this->inspectionService->getDispositions(),
        ]);
    }

    /**
     * Transfer inspection items to QC zone (quarantine or rejection warehouse)
     */
    public function transferToQcZone(Request $request, ReceivingInspection $receivingInspection): JsonResource
    {
        $validated = $request->validate([
            'target_warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $inspection = $this->inspectionService->transferToQcZone(
            $receivingInspection,
            $validated['target_warehouse_id']
        );

        return ReceivingInspectionResource::make($inspection)
            ->additional(['message' => 'Items transferred to QC zone successfully']);
    }
}
