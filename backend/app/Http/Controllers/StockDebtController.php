<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockDebtResource;
use App\Models\StockDebt;
use App\Services\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class StockDebtController extends Controller
{
    public function __construct(
        protected StockAlertService $alertService
    ) {}

    /**
     * Display a listing of stock debts
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockDebt::with(['product', 'warehouse', 'stockMovement'])
            ->where('company_id', Auth::user()->company_id);

        // Filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('outstanding_only') && $request->boolean('outstanding_only')) {
            $query->outstanding();
        }

        if ($request->has('reconciled_only') && $request->boolean('reconciled_only')) {
            $query->reconciled();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $debts = $query->paginate($perPage);

        return StockDebtResource::collection($debts);
    }

    /**
     * Display the specified stock debt
     */
    public function show(StockDebt $stockDebt): JsonResponse
    {
        $stockDebt->load(['product', 'warehouse', 'stockMovement', 'reference']);

        return response()->json([
            'data' => StockDebtResource::make($stockDebt),
        ]);
    }

    /**
     * Get negative stock alerts
     */
    public function alerts(): JsonResponse
    {
        $alerts = $this->alertService->getNegativeStockAlerts();

        return response()->json([
            'data' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Get weekly negative stock report
     */
    public function weeklyReport(): JsonResponse
    {
        $report = $this->alertService->getWeeklyNegativeStockReport();

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Get long-term negative stock (outstanding for more than threshold days)
     */
    public function longTerm(Request $request): JsonResponse
    {
        $thresholdDays = $request->get('threshold_days', 7);
        $longTermDebts = $this->alertService->checkLongTermNegativeStock($thresholdDays);

        return response()->json([
            'data' => $longTermDebts,
            'count' => $longTermDebts->count(),
            'threshold_days' => $thresholdDays,
        ]);
    }
}
