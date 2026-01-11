<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Services\BomService;
use App\Services\MrpCacheService;
use App\Http\Resources\BomResource;
use App\Http\Resources\BomListResource;
use App\Http\Resources\BomItemResource;
use App\Enums\BomType;
use App\Enums\BomStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;

class BomController extends Controller
{
    public function __construct(
        protected BomService $bomService,
        protected MrpCacheService $cacheService
    ) {}

    /**
     * Display a listing of BOMs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'product_id',
            'status',
            'bom_type',
            'is_default',
            'active_only',
        ]);
        $perPage = $request->get('per_page', 15);

        $boms = $this->bomService->getBoms($filters, $perPage);

        return BomListResource::collection($boms);
    }

    /**
     * Get all active BOMs for dropdowns
     */
    public function list(): JsonResponse
    {
        $boms = $this->bomService->getActiveBoms();

        return response()->json([
            'data' => BomListResource::collection($boms),
        ]);
    }

    /**
     * Get BOMs for a specific product
     */
    public function forProduct(int $productId): JsonResponse
    {
        $boms = $this->bomService->getBomsForProduct($productId);

        return response()->json([
            'data' => BomListResource::collection($boms),
        ]);
    }

    /**
     * Store a newly created BOM
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'bom_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'bom_type' => ['required', Rule::enum(BomType::class)],
            'quantity' => 'required|numeric|min:0.0001',
            'uom_id' => 'required|exists:units_of_measure,id',
            'is_default' => 'boolean',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.component_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.uom_id' => 'required|exists:units_of_measure,id',
            'items.*.scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.is_optional' => 'boolean',
            'items.*.is_phantom' => 'boolean',
            'items.*.notes' => 'nullable|string',
        ]);

        $bom = $this->bomService->create($validated);

        return response()->json([
            'message' => 'BOM created successfully',
            'data' => BomResource::make($bom),
        ], 201);
    }

    /**
     * Display the specified BOM
     */
    public function show(Bom $bom): JsonResource
    {
        return BomResource::make(
            $this->bomService->getBom($bom)
        );
    }

    /**
     * Update the specified BOM
     */
    public function update(Request $request, Bom $bom): JsonResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'bom_type' => ['sometimes', Rule::enum(BomType::class)],
            'quantity' => 'sometimes|numeric|min:0.0001',
            'uom_id' => 'sometimes|exists:units_of_measure,id',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ]);

        $bom = $this->bomService->update($bom, $validated);

        return BomResource::make($bom)
            ->additional(['message' => 'BOM updated successfully']);
    }

    /**
     * Remove the specified BOM
     */
    public function destroy(Bom $bom): JsonResponse
    {
        $this->bomService->delete($bom);

        return response()->json([
            'message' => 'BOM deleted successfully',
        ]);
    }

    /**
     * Add item to BOM
     */
    public function addItem(Request $request, Bom $bom): JsonResponse
    {
        $validated = $request->validate([
            'component_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.0001',
            'uom_id' => 'required|exists:units_of_measure,id',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'is_optional' => 'boolean',
            'is_phantom' => 'boolean',
            'line_number' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $item = $this->bomService->addItem($bom, $validated);

        return response()->json([
            'message' => 'Item added to BOM successfully',
            'data' => BomItemResource::make($item->load(['component', 'uom'])),
        ], 201);
    }

    /**
     * Update BOM item
     */
    public function updateItem(Request $request, Bom $bom, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'component_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|numeric|min:0.0001',
            'uom_id' => 'sometimes|exists:units_of_measure,id',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'is_optional' => 'boolean',
            'is_phantom' => 'boolean',
            'line_number' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $item = $this->bomService->updateItem($bom, $itemId, $validated);

        return response()->json([
            'message' => 'BOM item updated successfully',
            'data' => BomItemResource::make($item->load(['component', 'uom'])),
        ]);
    }

    /**
     * Remove item from BOM
     */
    public function removeItem(Bom $bom, int $itemId): JsonResponse
    {
        $this->bomService->removeItem($bom, $itemId);

        return response()->json([
            'message' => 'Item removed from BOM successfully',
        ]);
    }

    /**
     * Activate BOM
     */
    public function activate(Bom $bom): JsonResponse
    {
        $bom = $this->bomService->activate($bom);

        return response()->json([
            'message' => 'BOM activated successfully',
            'data' => BomResource::make($bom),
        ]);
    }

    /**
     * Mark BOM as obsolete
     */
    public function obsolete(Bom $bom): JsonResponse
    {
        $bom = $this->bomService->obsolete($bom);

        return response()->json([
            'message' => 'BOM marked as obsolete successfully',
            'data' => BomResource::make($bom),
        ]);
    }

    /**
     * Set BOM as default
     */
    public function setDefault(Bom $bom): JsonResponse
    {
        $bom = $this->bomService->setAsDefault($bom);

        return response()->json([
            'message' => 'BOM set as default successfully',
            'data' => BomResource::make($bom),
        ]);
    }

    /**
     * Copy BOM to new version
     */
    public function copy(Request $request, Bom $bom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $newBom = $this->bomService->copy($bom, $validated['name'] ?? null);

        return response()->json([
            'message' => 'BOM copied successfully',
            'data' => BomResource::make($newBom),
        ], 201);
    }

    /**
     * Explode BOM (multi-level only - all levels exploded)
     * 
     * Note: For single-level BOMs, use GET /api/boms/{bom} instead.
     * This endpoint always explodes all sub-BOMs recursively (phantom + regular items).
     */
    public function explode(Request $request, Bom $bom): JsonResponse
    {
        // Normalize boolean query parameters
        $request->merge([
            'include_optional' => $request->has('include_optional') 
                ? filter_var($request->input('include_optional'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : null,
            'aggregate_by_product' => $request->has('aggregate_by_product')
                ? filter_var($request->input('aggregate_by_product'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : null,
        ]);

        $validated = $request->validate([
            'quantity' => 'nullable|numeric|min:0.0001',
            'include_optional' => 'nullable|boolean',
            'aggregate_by_product' => 'nullable|boolean',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $includeOptional = $validated['include_optional'] ?? false;
        $explodeAllLevels = true; // Always explode all levels for this endpoint
        
        // Auto-detect aggregate_by_product based on BOM levels
        $aggregateByProduct = $validated['aggregate_by_product'] ?? null;
        
        // Check if BOM structure is cached (for determining single vs multi-level)
        // We use a simple flag to check structure without full explosion
        $structureCacheKey = "mrp:bom_structure:{$bom->id}";
        $hasMultiLevel = null;
        $cachedStructure = Redis::get($structureCacheKey);
        
        if ($cachedStructure !== null) {
            $hasMultiLevel = json_decode($cachedStructure, true)['has_multi_level'] ?? null;
        }
        
        // If structure not cached, do a quick explosion to determine it
        if ($hasMultiLevel === null) {
            $tempMaterials = $this->bomService->explodeBom($bom, 1, 10, false, false, false, false);
            $hasMultiLevel = false;
            foreach ($tempMaterials as $material) {
                if ($material['level'] > 0) {
                    $hasMultiLevel = true;
                    break;
                }
            }
            // Cache structure info for 1 hour
            Redis::setex($structureCacheKey, 3600, json_encode(['has_multi_level' => $hasMultiLevel]));
        }
        
        // Auto-detect aggregate_by_product based on BOM structure
        if ($aggregateByProduct === null) {
            // Single-level: default to aggregate for cleaner output
            // Multi-level: default to false for detailed tree
            $aggregateByProduct = !$hasMultiLevel;
        }
        
        $asTree = $hasMultiLevel; // Tree structure for multi-level
        
        // Check cache (quantity-independent, base structure for quantity=1)
        $baseMaterials = $this->cacheService->getCachedBomExplode(
            $bom->id,
            $includeOptional,
            $aggregateByProduct,
            $asTree
        );
        
        if ($baseMaterials === null) {
            // Cache miss - explode BOM with quantity=1 (base structure)
            $baseMaterials = $this->bomService->explodeBom(
                $bom, 
                1, // Base quantity = 1 for caching
                10, 
                $includeOptional, 
                $explodeAllLevels, // Always true
                $aggregateByProduct,
                $asTree // asTree: true for multi-level, false for single-level
            );
            
            // Cache the base structure (quantity=1)
            $this->cacheService->cacheBomExplode(
                $bom->id,
                $includeOptional,
                $aggregateByProduct,
                $asTree,
                $baseMaterials
            );
        }
        
        // Scale quantities if needed (quantity != 1)
        if (abs($quantity - 1.0) > 0.0001) {
            $materials = $this->cacheService->scaleExplosionQuantities($baseMaterials, $quantity);
        } else {
            $materials = $baseMaterials;
        }

        return response()->json([
            'data' => [
                'bom' => BomListResource::make($bom),
                'quantity' => $quantity,
                'include_optional' => $includeOptional,
                'aggregate_by_product' => $aggregateByProduct,
                'is_single_level' => !$hasMultiLevel,
                'structure' => $hasMultiLevel ? 'tree' : 'flat',
                'materials' => $materials,
                'total_materials' => $hasMultiLevel ? $this->countTreeItems($materials) : count($materials),
            ],
        ]);
    }
    
    /**
     * Count total items in tree structure
     */
    protected function countTreeItems(array $tree): int
    {
        $count = 0;
        foreach ($tree as $item) {
            $count++;
            if (isset($item['children']) && is_array($item['children'])) {
                $count += $this->countTreeItems($item['children']);
            }
        }
        return $count;
    }

    /**
     * Get BOM types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => BomType::options(),
        ]);
    }

    /**
     * Get BOM statuses
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => BomStatus::options(),
        ]);
    }
}
