<?php

namespace App\Http\Controllers;

use App\Enums\UomType;
use App\Models\UnitOfMeasure;
use App\Services\UnitOfMeasureService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UnitOfMeasureController extends Controller
{
    protected UnitOfMeasureService $uomService;

    public function __construct(UnitOfMeasureService $uomService)
    {
        $this->uomService = $uomService;
    }

    /**
     * Display a listing of units of measure
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'is_active',
            'uom_type',
            'base_units_only',
        ]);
        $perPage = $request->get('per_page', 15);

        $units = $this->uomService->getUnitsOfMeasure($filters, $perPage);

        return response()->json($units);
    }

    /**
     * Get all units grouped by type (for dropdowns)
     */
    public function grouped(): JsonResponse
    {
        $grouped = $this->uomService->getGroupedByType();

        return response()->json([
            'data' => $grouped,
            'types' => UomType::options(),
        ]);
    }

    /**
     * Store a newly created unit of measure
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'uom_type' => ['required', Rule::enum(UomType::class)],
            'base_unit_id' => 'nullable|exists:units_of_measure,id',
            'conversion_factor' => 'nullable|numeric|min:0',
            'precision' => 'integer|min:0|max:6',
            'is_active' => 'boolean',
        ]);

        $uom = $this->uomService->create($validated);

        return response()->json([
            'message' => 'Unit of measure created successfully',
            'data' => $uom,
        ], 201);
    }

    /**
     * Display the specified unit of measure
     */
    public function show(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        return response()->json([
            'data' => $this->uomService->getUnitOfMeasure($unitOfMeasure),
        ]);
    }

    /**
     * Update the specified unit of measure
     */
    public function update(Request $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:20',
            'name' => 'sometimes|required|string|max:100',
            'uom_type' => ['sometimes', 'required', Rule::enum(UomType::class)],
            'base_unit_id' => 'nullable|exists:units_of_measure,id',
            'conversion_factor' => 'nullable|numeric|min:0',
            'precision' => 'integer|min:0|max:6',
            'is_active' => 'boolean',
        ]);

        $uom = $this->uomService->update($unitOfMeasure, $validated);

        return response()->json([
            'message' => 'Unit of measure updated successfully',
            'data' => $uom,
        ]);
    }

    /**
     * Remove the specified unit of measure
     */
    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $this->uomService->delete($unitOfMeasure);

        return response()->json([
            'message' => 'Unit of measure deleted successfully',
        ]);
    }

    /**
     * Toggle unit active status
     */
    public function toggleActive(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $uom = $this->uomService->toggleActive($unitOfMeasure);

        return response()->json([
            'message' => 'Unit of measure status updated successfully',
            'data' => $uom,
        ]);
    }

    /**
     * Convert quantity between units
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric',
            'from_unit_id' => 'required|exists:units_of_measure,id',
            'to_unit_id' => 'required|exists:units_of_measure,id',
        ]);

        $fromUnit = UnitOfMeasure::findOrFail($validated['from_unit_id']);
        $toUnit = UnitOfMeasure::findOrFail($validated['to_unit_id']);

        $result = $this->uomService->convert(
            $validated['quantity'],
            $fromUnit,
            $toUnit
        );

        if ($result === null) {
            return response()->json([
                'message' => 'Cannot convert between these units. They may be of different types or missing conversion factors.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'from' => [
                    'quantity' => $validated['quantity'],
                    'unit' => $fromUnit->code,
                ],
                'to' => [
                    'quantity' => $result,
                    'unit' => $toUnit->code,
                    'formatted' => $toUnit->formatQuantity($result) . ' ' . $toUnit->code,
                ],
            ],
        ]);
    }
}
