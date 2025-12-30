<?php

namespace App\Http\Controllers;

use App\Models\Routing;
use App\Services\RoutingService;
use App\Http\Resources\RoutingResource;
use App\Http\Resources\RoutingListResource;
use App\Http\Resources\RoutingOperationResource;
use App\Enums\RoutingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class RoutingController extends Controller
{
    public function __construct(
        protected RoutingService $routingService
    ) {}

    /**
     * Display a listing of routings
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'product_id',
            'status',
            'is_default',
            'active_only',
        ]);
        $perPage = $request->get('per_page', 15);

        $routings = $this->routingService->getRoutings($filters, $perPage);

        return RoutingListResource::collection($routings);
    }

    /**
     * Get all active routings for dropdowns
     */
    public function list(): JsonResponse
    {
        $routings = $this->routingService->getActiveRoutings();

        return response()->json([
            'data' => RoutingListResource::collection($routings),
        ]);
    }

    /**
     * Get routings for a specific product
     */
    public function forProduct(int $productId): JsonResponse
    {
        $routings = $this->routingService->getRoutingsForProduct($productId);

        return response()->json([
            'data' => RoutingListResource::collection($routings),
        ]);
    }

    /**
     * Store a newly created routing
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'routing_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'operations' => 'nullable|array',
            'operations.*.work_center_id' => 'required|exists:work_centers,id',
            'operations.*.operation_number' => 'nullable|integer|min:1',
            'operations.*.name' => 'required|string|max:255',
            'operations.*.description' => 'nullable|string',
            'operations.*.setup_time' => 'nullable|numeric|min:0',
            'operations.*.run_time_per_unit' => 'nullable|numeric|min:0',
            'operations.*.queue_time' => 'nullable|numeric|min:0',
            'operations.*.move_time' => 'nullable|numeric|min:0',
            'operations.*.is_subcontracted' => 'boolean',
            'operations.*.subcontractor_id' => 'nullable|exists:suppliers,id',
            'operations.*.subcontract_cost' => 'nullable|numeric|min:0',
            'operations.*.instructions' => 'nullable|string',
        ]);

        $routing = $this->routingService->create($validated);

        return response()->json([
            'message' => 'Routing created successfully',
            'data' => RoutingResource::make($routing),
        ], 201);
    }

    /**
     * Display the specified routing
     */
    public function show(Routing $routing): JsonResource
    {
        return RoutingResource::make(
            $this->routingService->getRouting($routing)
        );
    }

    /**
     * Update the specified routing
     */
    public function update(Request $request, Routing $routing): JsonResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $routing = $this->routingService->update($routing, $validated);

        return RoutingResource::make($routing)
            ->additional(['message' => 'Routing updated successfully']);
    }

    /**
     * Remove the specified routing
     */
    public function destroy(Routing $routing): JsonResponse
    {
        $this->routingService->delete($routing);

        return response()->json([
            'message' => 'Routing deleted successfully',
        ]);
    }

    /**
     * Add operation to routing
     */
    public function addOperation(Request $request, Routing $routing): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => 'required|exists:work_centers,id',
            'operation_number' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'setup_time' => 'nullable|numeric|min:0',
            'run_time_per_unit' => 'nullable|numeric|min:0',
            'queue_time' => 'nullable|numeric|min:0',
            'move_time' => 'nullable|numeric|min:0',
            'is_subcontracted' => 'boolean',
            'subcontractor_id' => 'nullable|exists:suppliers,id',
            'subcontract_cost' => 'nullable|numeric|min:0',
            'instructions' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $operation = $this->routingService->addOperation($routing, $validated);

        return response()->json([
            'message' => 'Operation added to routing successfully',
            'data' => RoutingOperationResource::make($operation->load('workCenter')),
        ], 201);
    }

    /**
     * Update routing operation
     */
    public function updateOperation(Request $request, Routing $routing, int $operationId): JsonResponse
    {
        $validated = $request->validate([
            'work_center_id' => 'sometimes|exists:work_centers,id',
            'operation_number' => 'nullable|integer|min:1',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'setup_time' => 'nullable|numeric|min:0',
            'run_time_per_unit' => 'nullable|numeric|min:0',
            'queue_time' => 'nullable|numeric|min:0',
            'move_time' => 'nullable|numeric|min:0',
            'is_subcontracted' => 'boolean',
            'subcontractor_id' => 'nullable|exists:suppliers,id',
            'subcontract_cost' => 'nullable|numeric|min:0',
            'instructions' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $operation = $this->routingService->updateOperation($routing, $operationId, $validated);

        return response()->json([
            'message' => 'Routing operation updated successfully',
            'data' => RoutingOperationResource::make($operation->load('workCenter')),
        ]);
    }

    /**
     * Remove operation from routing
     */
    public function removeOperation(Routing $routing, int $operationId): JsonResponse
    {
        $this->routingService->removeOperation($routing, $operationId);

        return response()->json([
            'message' => 'Operation removed from routing successfully',
        ]);
    }

    /**
     * Reorder operations
     */
    public function reorderOperations(Request $request, Routing $routing): JsonResponse
    {
        $validated = $request->validate([
            'operation_ids' => 'required|array',
            'operation_ids.*' => 'required|integer|exists:routing_operations,id',
        ]);

        $this->routingService->reorderOperations($routing, $validated['operation_ids']);

        return response()->json([
            'message' => 'Operations reordered successfully',
            'data' => RoutingResource::make($routing->fresh('operations')),
        ]);
    }

    /**
     * Activate routing
     */
    public function activate(Routing $routing): JsonResponse
    {
        $routing = $this->routingService->activate($routing);

        return response()->json([
            'message' => 'Routing activated successfully',
            'data' => RoutingResource::make($routing),
        ]);
    }

    /**
     * Mark routing as obsolete
     */
    public function obsolete(Routing $routing): JsonResponse
    {
        $routing = $this->routingService->obsolete($routing);

        return response()->json([
            'message' => 'Routing marked as obsolete successfully',
            'data' => RoutingResource::make($routing),
        ]);
    }

    /**
     * Set routing as default
     */
    public function setDefault(Routing $routing): JsonResponse
    {
        $routing = $this->routingService->setAsDefault($routing);

        return response()->json([
            'message' => 'Routing set as default successfully',
            'data' => RoutingResource::make($routing),
        ]);
    }

    /**
     * Copy routing to new version
     */
    public function copy(Request $request, Routing $routing): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $newRouting = $this->routingService->copy($routing, $validated['name'] ?? null);

        return response()->json([
            'message' => 'Routing copied successfully',
            'data' => RoutingResource::make($newRouting),
        ], 201);
    }

    /**
     * Calculate lead time
     */
    public function calculateLeadTime(Request $request, Routing $routing): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $leadTime = $this->routingService->calculateLeadTime($routing, $validated['quantity']);

        return response()->json([
            'data' => [
                'routing' => RoutingListResource::make($routing),
                'quantity' => $validated['quantity'],
                'lead_time' => $leadTime,
            ],
        ]);
    }

    /**
     * Get routing statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => RoutingStatus::options(),
        ]);
    }
}
