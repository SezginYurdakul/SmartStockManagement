<?php

namespace App\Http\Controllers;

use App\Models\AcceptanceRule;
use App\Services\AcceptanceRuleService;
use App\Http\Resources\AcceptanceRuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class AcceptanceRuleController extends Controller
{
    public function __construct(
        protected AcceptanceRuleService $acceptanceRuleService
    ) {}

    /**
     * Display a listing of acceptance rules
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'is_active',
            'inspection_type',
            'product_id',
            'category_id',
            'supplier_id',
        ]);
        $perPage = $request->get('per_page', 15);

        $rules = $this->acceptanceRuleService->getAcceptanceRules($filters, $perPage);

        return AcceptanceRuleResource::collection($rules);
    }

    /**
     * Get all active rules for dropdowns
     */
    public function list(): JsonResponse
    {
        $rules = $this->acceptanceRuleService->getList();

        return response()->json([
            'data' => $rules,
        ]);
    }

    /**
     * Store a newly created acceptance rule
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'inspection_type' => ['required', Rule::in(array_keys(AcceptanceRule::getInspectionTypes()))],
            'sampling_method' => ['required', Rule::in(array_keys(AcceptanceRule::getSamplingMethods()))],
            'sample_size_percentage' => 'nullable|numeric|min:0|max:100',
            'aql_level' => 'nullable|string|max:20',
            'aql_value' => 'nullable|numeric|min:0|max:10',
            'criteria' => 'nullable|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $rule = $this->acceptanceRuleService->create($validated);

        return response()->json([
            'message' => 'Acceptance rule created successfully',
            'data' => AcceptanceRuleResource::make($rule),
        ], 201);
    }

    /**
     * Display the specified acceptance rule
     */
    public function show(AcceptanceRule $acceptanceRule): JsonResource
    {
        return AcceptanceRuleResource::make(
            $this->acceptanceRuleService->getAcceptanceRule($acceptanceRule)
        );
    }

    /**
     * Update the specified acceptance rule
     */
    public function update(Request $request, AcceptanceRule $acceptanceRule): JsonResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'inspection_type' => ['sometimes', 'required', Rule::in(array_keys(AcceptanceRule::getInspectionTypes()))],
            'sampling_method' => ['sometimes', 'required', Rule::in(array_keys(AcceptanceRule::getSamplingMethods()))],
            'sample_size_percentage' => 'nullable|numeric|min:0|max:100',
            'aql_level' => 'nullable|string|max:20',
            'aql_value' => 'nullable|numeric|min:0|max:10',
            'criteria' => 'nullable|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $rule = $this->acceptanceRuleService->update($acceptanceRule, $validated);

        return AcceptanceRuleResource::make($rule)
            ->additional(['message' => 'Acceptance rule updated successfully']);
    }

    /**
     * Remove the specified acceptance rule
     */
    public function destroy(AcceptanceRule $acceptanceRule): JsonResponse
    {
        $this->acceptanceRuleService->delete($acceptanceRule);

        return response()->json([
            'message' => 'Acceptance rule deleted successfully',
        ]);
    }

    /**
     * Find applicable rule for product/supplier
     */
    public function findApplicable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $rule = $this->acceptanceRuleService->findApplicableRule(
            $validated['product_id'],
            $validated['supplier_id'] ?? null
        );

        return response()->json([
            'data' => $rule ? AcceptanceRuleResource::make($rule) : null,
        ]);
    }

    /**
     * Get inspection types for dropdown
     */
    public function inspectionTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->acceptanceRuleService->getInspectionTypes(),
        ]);
    }

    /**
     * Get sampling methods for dropdown
     */
    public function samplingMethods(): JsonResponse
    {
        return response()->json([
            'data' => $this->acceptanceRuleService->getSamplingMethods(),
        ]);
    }
}
