<?php

namespace App\Http\Controllers;

use App\Models\WorkCenter;
use App\Services\WorkCenterService;
use App\Http\Resources\WorkCenterResource;
use App\Http\Resources\WorkCenterListResource;
use App\Enums\WorkCenterType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class WorkCenterController extends Controller
{
    public function __construct(
        protected WorkCenterService $workCenterService
    ) {}

    /**
     * Display a listing of work centers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'is_active',
            'work_center_type',
        ]);
        $perPage = $request->get('per_page', 15);

        $workCenters = $this->workCenterService->getWorkCenters($filters, $perPage);

        return WorkCenterListResource::collection($workCenters);
    }

    /**
     * Get all active work centers for dropdowns
     */
    public function list(): JsonResponse
    {
        $workCenters = $this->workCenterService->getActiveWorkCenters();

        return response()->json([
            'data' => WorkCenterListResource::collection($workCenters),
        ]);
    }

    /**
     * Store a newly created work center
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'work_center_type' => ['required', Rule::enum(WorkCenterType::class)],
            'cost_per_hour' => 'nullable|numeric|min:0',
            'cost_currency' => 'nullable|string|size:3',
            'capacity_per_day' => 'nullable|numeric|min:0',
            'efficiency_percentage' => 'nullable|numeric|min:0|max:200',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $workCenter = $this->workCenterService->create($validated);

        return response()->json([
            'message' => 'Work center created successfully',
            'data' => WorkCenterResource::make($workCenter),
        ], 201);
    }

    /**
     * Display the specified work center
     */
    public function show(WorkCenter $workCenter): JsonResource
    {
        return WorkCenterResource::make(
            $this->workCenterService->getWorkCenter($workCenter)
        );
    }

    /**
     * Update the specified work center
     */
    public function update(Request $request, WorkCenter $workCenter): JsonResource
    {
        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('work_centers')->where(function ($query) use ($workCenter) {
                    return $query->where('company_id', $workCenter->company_id);
                })->ignore($workCenter->id),
            ],
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'work_center_type' => ['sometimes', Rule::enum(WorkCenterType::class)],
            'cost_per_hour' => 'nullable|numeric|min:0',
            'cost_currency' => 'nullable|string|size:3',
            'capacity_per_day' => 'nullable|numeric|min:0',
            'efficiency_percentage' => 'nullable|numeric|min:0|max:200',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $workCenter = $this->workCenterService->update($workCenter, $validated);

        return WorkCenterResource::make($workCenter)
            ->additional(['message' => 'Work center updated successfully']);
    }

    /**
     * Remove the specified work center
     */
    public function destroy(WorkCenter $workCenter): JsonResponse
    {
        $this->workCenterService->delete($workCenter);

        return response()->json([
            'message' => 'Work center deleted successfully',
        ]);
    }

    /**
     * Toggle work center active status
     */
    public function toggleActive(WorkCenter $workCenter): JsonResponse
    {
        $workCenter = $this->workCenterService->toggleActive($workCenter);

        return response()->json([
            'message' => 'Work center status updated successfully',
            'data' => WorkCenterResource::make($workCenter),
        ]);
    }

    /**
     * Get work center availability
     */
    public function availability(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $availability = $this->workCenterService->getAvailability(
            $workCenter,
            new \DateTime($validated['start_date']),
            new \DateTime($validated['end_date'])
        );

        return response()->json([
            'data' => $availability,
        ]);
    }

    /**
     * Get work center types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => WorkCenterType::options(),
        ]);
    }
}
