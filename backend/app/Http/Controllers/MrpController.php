<?php

namespace App\Http\Controllers;

use App\Models\MrpRun;
use App\Models\MrpRecommendation;
use App\Services\MrpService;
use App\Http\Resources\MrpRunResource;
use App\Http\Resources\MrpRunListResource;
use App\Http\Resources\MrpRecommendationResource;
use App\Enums\MrpRunStatus;
use App\Enums\MrpRecommendationType;
use App\Enums\MrpRecommendationStatus;
use App\Enums\MrpPriority;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MrpController extends Controller
{
    public function __construct(
        protected MrpService $mrpService
    ) {}

    // =========================================
    // MRP Runs
    // =========================================

    /**
     * List MRP runs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'from_date', 'to_date']);
        $perPage = $request->get('per_page', 15);

        $runs = $this->mrpService->getRuns($filters, $perPage);

        return MrpRunListResource::collection($runs);
    }

    /**
     * Get MRP statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->mrpService->getStatistics();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Create and execute a new MRP run
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'planning_horizon_start' => 'nullable|date',
            'planning_horizon_end' => 'nullable|date|after_or_equal:planning_horizon_start',
            'include_safety_stock' => 'nullable|boolean',
            'respect_lead_times' => 'nullable|boolean',
            'consider_wip' => 'nullable|boolean',
            'net_change' => 'nullable|boolean',
            'async' => 'nullable|boolean', // Force async mode
            'product_filters' => 'nullable|array',
            'product_filters.product_ids' => 'nullable|array',
            'product_filters.product_ids.*' => 'integer|exists:products,id',
            'product_filters.category_ids' => 'nullable|array',
            'product_filters.category_ids.*' => 'integer|exists:categories,id',
            'product_filters.make_or_buy' => 'nullable|string|in:make,buy',
            'warehouse_filters' => 'nullable|array',
            'warehouse_filters.include' => 'nullable|array',
            'warehouse_filters.include.*' => 'integer|exists:warehouses,id',
            'warehouse_filters.exclude' => 'nullable|array',
            'warehouse_filters.exclude.*' => 'integer|exists:warehouses,id',
        ]);

        $async = $validated['async'] ?? null;
        unset($validated['async']);

        $run = $this->mrpService->runMrp($validated, $async);

        $message = $run->status->value === 'running'
            ? 'MRP run started and is processing in the background'
            : 'MRP run completed successfully';

        return response()->json([
            'message' => $message,
            'data' => MrpRunResource::make($run),
        ], 201);
    }

    /**
     * Get a specific MRP run
     */
    public function show(MrpRun $mrpRun): JsonResponse
    {
        $run = $this->mrpService->getRun($mrpRun);

        return response()->json([
            'data' => MrpRunResource::make($run),
        ]);
    }

    /**
     * Get MRP run progress (for long-running calculations)
     */
    public function progress(MrpRun $mrpRun): JsonResponse
    {
        $progress = $this->mrpService->getRunProgress($mrpRun);

        if ($progress === null) {
            return response()->json([
                'message' => 'No progress information available',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Invalidate MRP cache (call when BOMs or product structures change)
     */
    public function invalidateCache(): JsonResponse
    {
        $this->mrpService->invalidateCache();

        return response()->json([
            'message' => 'MRP cache invalidated successfully',
        ]);
    }

    /**
     * Cancel an MRP run
     */
    public function cancel(MrpRun $mrpRun): JsonResponse
    {
        $run = $this->mrpService->cancelRun($mrpRun);

        return response()->json([
            'message' => 'MRP run cancelled',
            'data' => MrpRunResource::make($run),
        ]);
    }

    // =========================================
    // MRP Recommendations
    // =========================================

    /**
     * Get recommendations for an MRP run
     */
    public function recommendations(Request $request, MrpRun $mrpRun): AnonymousResourceCollection
    {
        $filters = $request->only([
            'status',
            'type',
            'priority',
            'product_id',
            'urgent_only',
        ]);
        $perPage = $request->get('per_page', 25);

        $recommendations = $this->mrpService->getRecommendations($mrpRun, $filters, $perPage);

        return MrpRecommendationResource::collection($recommendations);
    }

    /**
     * Approve a recommendation
     */
    public function approveRecommendation(MrpRecommendation $recommendation): JsonResponse
    {
        $recommendation = $this->mrpService->approveRecommendation($recommendation);

        return response()->json([
            'message' => 'Recommendation approved',
            'data' => MrpRecommendationResource::make($recommendation),
        ]);
    }

    /**
     * Reject a recommendation
     */
    public function rejectRecommendation(Request $request, MrpRecommendation $recommendation): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $recommendation = $this->mrpService->rejectRecommendation(
            $recommendation,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Recommendation rejected',
            'data' => MrpRecommendationResource::make($recommendation),
        ]);
    }

    /**
     * Bulk approve recommendations
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:mrp_recommendations,id',
        ]);

        $count = $this->mrpService->bulkApprove($validated['ids']);

        return response()->json([
            'message' => "{$count} recommendations approved",
            'approved_count' => $count,
        ]);
    }

    /**
     * Bulk reject recommendations
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:mrp_recommendations,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $count = $this->mrpService->bulkReject($validated['ids'], $validated['notes'] ?? null);

        return response()->json([
            'message' => "{$count} recommendations rejected",
            'rejected_count' => $count,
        ]);
    }

    // =========================================
    // Helper Endpoints
    // =========================================

    /**
     * Get products needing attention
     */
    public function productsNeedingAttention(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $products = $this->mrpService->getProductsNeedingAttention($limit);

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Get MRP run statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => MrpRunStatus::options(),
        ]);
    }

    /**
     * Get recommendation types
     */
    public function recommendationTypes(): JsonResponse
    {
        return response()->json([
            'data' => MrpRecommendationType::options(),
        ]);
    }

    /**
     * Get recommendation statuses
     */
    public function recommendationStatuses(): JsonResponse
    {
        return response()->json([
            'data' => MrpRecommendationStatus::options(),
        ]);
    }

    /**
     * Get MRP priorities
     */
    public function priorities(): JsonResponse
    {
        return response()->json([
            'data' => MrpPriority::options(),
        ]);
    }
}
