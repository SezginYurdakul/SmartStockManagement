<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Product;
use App\Enums\BomStatus;
use App\Enums\BomType;
use App\Exceptions\BusinessException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BomService
{
    /**
     * Get paginated BOMs with filters
     */
    public function getBoms(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Bom::with(['product:id,name,sku', 'uom:id,code,name', 'creator:id,first_name,last_name'])
            ->withCount('items');

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Product filter
        if (!empty($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Type filter
        if (!empty($filters['bom_type'])) {
            $query->where('bom_type', $filters['bom_type']);
        }

        // Default only
        if (!empty($filters['is_default'])) {
            $query->default();
        }

        // Active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        return $query->orderBy('bom_number')->paginate($perPage);
    }

    /**
     * Get all active BOMs for dropdowns
     */
    public function getActiveBoms(): Collection
    {
        return Bom::active()
            ->with('product:id,name,sku')
            ->orderBy('bom_number')
            ->get(['id', 'bom_number', 'name', 'product_id', 'version']);
    }

    /**
     * Get BOMs for a specific product
     */
    public function getBomsForProduct(int $productId): Collection
    {
        return Bom::forProduct($productId)
            ->with(['uom:id,code,name'])
            ->withCount('items')
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Get BOM with full relationships
     */
    public function getBom(Bom $bom): Bom
    {
        return $bom->load([
            'product:id,name,sku',
            'uom:id,code,name',
            'creator:id,first_name,last_name',
            'items.component:id,name,sku',
            'items.uom:id,code,name',
        ]);
    }

    /**
     * Create a new BOM
     */
    public function create(array $data): Bom
    {
        Log::info('Creating BOM', [
            'product_id' => $data['product_id'] ?? null,
            'bom_number' => $data['bom_number'] ?? null,
        ]);

        // Validate product can have BOM
        $product = Product::with('productType')->findOrFail($data['product_id']);
        if (!$product->canHaveBom()) {
            throw new BusinessException(
                "Product '{$product->name}' cannot have a BOM. Product type must allow manufacturing."
            );
        }

        return DB::transaction(function () use ($data) {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();

            // Generate BOM number if not provided
            if (empty($data['bom_number'])) {
                $data['bom_number'] = $this->generateBomNumber();
            }

            // If this is the first BOM for the product, make it default
            $existingCount = Bom::where('product_id', $data['product_id'])
                ->where('company_id', $data['company_id'])
                ->count();

            if ($existingCount === 0) {
                $data['is_default'] = true;
            }

            $bom = Bom::create($data);

            // Create items if provided
            if (!empty($data['items'])) {
                $this->createItems($bom, $data['items']);
            }

            Log::info('BOM created', ['id' => $bom->id, 'bom_number' => $bom->bom_number]);

            return $bom->fresh(['items']);
        });
    }

    /**
     * Update BOM
     */
    public function update(Bom $bom, array $data): Bom
    {
        if (!$bom->canEdit()) {
            throw new BusinessException("BOM cannot be edited in {$bom->status->label()} status.");
        }

        Log::info('Updating BOM', [
            'id' => $bom->id,
            'changes' => array_keys($data),
        ]);

        $bom->update($data);

        return $bom->fresh();
    }

    /**
     * Delete BOM
     */
    public function delete(Bom $bom): bool
    {
        if ($bom->workOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            throw new BusinessException("Cannot delete BOM with active work orders.");
        }

        Log::info('Deleting BOM', ['id' => $bom->id]);

        return $bom->delete();
    }

    /**
     * Add item to BOM
     */
    public function addItem(Bom $bom, array $data): BomItem
    {
        if (!$bom->canEdit()) {
            throw new BusinessException("Cannot add items to BOM in {$bom->status->label()} status.");
        }

        // Validate no circular reference
        $this->validateNoCircularReference($bom->product_id, $data['component_id']);

        // Get next line number
        $nextLineNumber = ($bom->items()->max('line_number') ?? 0) + 1;
        $data['line_number'] = $data['line_number'] ?? $nextLineNumber;
        $data['bom_id'] = $bom->id;

        Log::info('Adding BOM item', [
            'bom_id' => $bom->id,
            'component_id' => $data['component_id'],
        ]);

        return BomItem::create($data);
    }

    /**
     * Update BOM item
     */
    public function updateItem(Bom $bom, int $itemId, array $data): BomItem
    {
        if (!$bom->canEdit()) {
            throw new BusinessException("Cannot update items in BOM in {$bom->status->label()} status.");
        }

        $item = $bom->items()->findOrFail($itemId);

        // Validate no circular reference if component changed
        if (isset($data['component_id']) && $data['component_id'] !== $item->component_id) {
            $this->validateNoCircularReference($bom->product_id, $data['component_id']);
        }

        Log::info('Updating BOM item', ['bom_id' => $bom->id, 'item_id' => $itemId]);

        $item->update($data);

        return $item->fresh();
    }

    /**
     * Remove item from BOM
     */
    public function removeItem(Bom $bom, int $itemId): bool
    {
        if (!$bom->canEdit()) {
            throw new BusinessException("Cannot remove items from BOM in {$bom->status->label()} status.");
        }

        $item = $bom->items()->findOrFail($itemId);

        Log::info('Removing BOM item', ['bom_id' => $bom->id, 'item_id' => $itemId]);

        return $item->delete();
    }

    /**
     * Activate BOM
     */
    public function activate(Bom $bom): Bom
    {
        if (!$bom->status->canTransitionTo(BomStatus::ACTIVE)) {
            throw new BusinessException("Cannot activate BOM from {$bom->status->label()} status.");
        }

        if ($bom->items()->count() === 0) {
            throw new BusinessException("Cannot activate BOM without items.");
        }

        Log::info('Activating BOM', ['id' => $bom->id]);

        $bom->update(['status' => BomStatus::ACTIVE]);

        return $bom->fresh();
    }

    /**
     * Mark BOM as obsolete
     */
    public function obsolete(Bom $bom): Bom
    {
        if (!$bom->status->canTransitionTo(BomStatus::OBSOLETE)) {
            throw new BusinessException("Cannot mark BOM as obsolete from {$bom->status->label()} status.");
        }

        Log::info('Marking BOM as obsolete', ['id' => $bom->id]);

        $bom->update([
            'status' => BomStatus::OBSOLETE,
            'is_default' => false,
        ]);

        return $bom->fresh();
    }

    /**
     * Set BOM as default for product
     */
    public function setAsDefault(Bom $bom): Bom
    {
        if ($bom->status !== BomStatus::ACTIVE) {
            throw new BusinessException("Only active BOMs can be set as default.");
        }

        Log::info('Setting BOM as default', ['id' => $bom->id, 'product_id' => $bom->product_id]);

        DB::transaction(function () use ($bom) {
            // Remove default from other BOMs of same product
            Bom::where('product_id', $bom->product_id)
                ->where('id', '!=', $bom->id)
                ->update(['is_default' => false]);

            $bom->update(['is_default' => true]);
        });

        return $bom->fresh();
    }

    /**
     * Copy BOM to new version
     */
    public function copy(Bom $bom, ?string $newName = null): Bom
    {
        Log::info('Copying BOM', ['source_id' => $bom->id]);

        return DB::transaction(function () use ($bom, $newName) {
            // Get next version number
            $nextVersion = Bom::where('product_id', $bom->product_id)
                ->where('company_id', $bom->company_id)
                ->max('version') + 1;

            // Create new BOM
            $newBom = Bom::create([
                'company_id' => $bom->company_id,
                'product_id' => $bom->product_id,
                'bom_number' => $this->generateBomNumber(),
                'version' => $nextVersion,
                'name' => $newName ?? "{$bom->name} (Copy)",
                'description' => $bom->description,
                'bom_type' => $bom->bom_type,
                'status' => BomStatus::DRAFT,
                'quantity' => $bom->quantity,
                'uom_id' => $bom->uom_id,
                'is_default' => false,
                'notes' => $bom->notes,
                'created_by' => Auth::id(),
            ]);

            // Copy items
            foreach ($bom->items as $item) {
                BomItem::create([
                    'bom_id' => $newBom->id,
                    'component_id' => $item->component_id,
                    'line_number' => $item->line_number,
                    'quantity' => $item->quantity,
                    'uom_id' => $item->uom_id,
                    'scrap_percentage' => $item->scrap_percentage,
                    'is_optional' => $item->is_optional,
                    'is_phantom' => $item->is_phantom,
                    'notes' => $item->notes,
                ]);
            }

            Log::info('BOM copied', ['source_id' => $bom->id, 'new_id' => $newBom->id]);

            return $newBom->fresh(['items']);
        });
    }

    /**
     * Explode BOM (multi-level)
     * Returns flat list of all required materials
     *
     * @param Bom $bom The BOM to explode
     * @param float $quantity The quantity to produce
     * @param int $maxLevel Maximum recursion depth
     * @param bool $includeOptional Whether to include optional items
     * @param bool $explodeAllLevels Whether to explode all sub-BOMs (not just phantoms)
     * @param bool $aggregateByProduct Whether to aggregate quantities by product_id
     */
    public function explodeBom(Bom $bom, float $quantity = 1, int $maxLevel = 10, bool $includeOptional = false, bool $explodeAllLevels = false, bool $aggregateByProduct = false, bool $asTree = false): array
    {
        $materials = $this->explodeBomRecursive($bom, $quantity, 0, $maxLevel, [], $includeOptional, $explodeAllLevels);
        
        // Check if multi-level
        $hasMultiLevel = false;
        foreach ($materials as $material) {
            if ($material['level'] > 0) {
                $hasMultiLevel = true;
                break;
            }
        }
        
        // Single level: return aggregated summary
        if (!$hasMultiLevel && $aggregateByProduct) {
            return $this->aggregateMaterialsByProduct($materials);
        }
        
        // Multi-level: return tree structure if requested
        if ($hasMultiLevel && $asTree) {
            return $this->buildTreeStructure($materials);
        }
        
        // Default: return flat list
        return $materials;
    }
    
    /**
     * Build hierarchical tree structure from flat material list
     */
    protected function buildTreeStructure(array $materials): array
    {
        // Create a map: parent_product_id => [children]
        $childrenMap = [];
        $rootItems = [];
        
        foreach ($materials as $material) {
            $parentId = $material['parent_product_id'] ?? null;
            
            // Clean up internal fields
            $node = $material;
            unset($node['parent_product_id']);
            unset($node['has_children']);
            
            if ($parentId === null) {
                // Root level item
                $rootItems[] = $node;
            } else {
                // Child item - group by parent
                if (!isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }
                $childrenMap[$parentId][] = $node;
            }
        }
        
        // Recursively attach children to parents
        $attachChildren = function(&$items) use (&$attachChildren, &$childrenMap) {
            foreach ($items as &$item) {
                $productId = $item['product_id'];
                if (isset($childrenMap[$productId])) {
                    $item['children'] = $childrenMap[$productId];
                    // Recursively attach children's children
                    $attachChildren($item['children']);
                }
            }
        };
        
        $attachChildren($rootItems);
        
        return $rootItems;
    }
    
    /**
     * Aggregate materials by product_id, summing quantities
     */
    public function aggregateMaterialsByProduct(array $materials): array
    {
        $aggregated = [];
        
        foreach ($materials as $material) {
            $productId = $material['product_id'];
            
            if (!isset($aggregated[$productId])) {
                // First occurrence - use as base
                $aggregated[$productId] = $material;
                // Don't add sources yet - we'll add it only if there are multiple sources
                unset($aggregated[$productId]['sources']);
            } else {
                // Multiple sources detected - initialize sources array if not exists
                if (!isset($aggregated[$productId]['sources'])) {
                    // Add the first source
                    $aggregated[$productId]['sources'] = [
                        [
                            'bom_id' => $aggregated[$productId]['bom_id'],
                            'bom_number' => $aggregated[$productId]['bom_number'],
                            'bom_name' => $aggregated[$productId]['bom_name'],
                            'level' => $aggregated[$productId]['level'],
                            'quantity' => $aggregated[$productId]['quantity'],
                        ]
                    ];
                }
                
                // Sum quantities
                $aggregated[$productId]['quantity'] += $material['quantity'];
                
                // Track sources (which BOMs contribute to this product)
                $aggregated[$productId]['sources'][] = [
                    'bom_id' => $material['bom_id'],
                    'bom_number' => $material['bom_number'],
                    'bom_name' => $material['bom_name'],
                    'level' => $material['level'],
                    'quantity' => $material['quantity'],
                ];
                
                // Keep the highest level (most detailed)
                if ($material['level'] > $aggregated[$productId]['level']) {
                    $aggregated[$productId]['level'] = $material['level'];
                }
            }
        }
        
        // Round final quantities
        foreach ($aggregated as &$item) {
            $item['quantity'] = round($item['quantity'], 4);
        }
        
        return array_values($aggregated);
    }

    /**
     * Recursive BOM explosion
     * Returns flat list with parent tracking for tree structure
     */
    protected function explodeBomRecursive(Bom $bom, float $quantity, int $level, int $maxLevel, array $visited, bool $includeOptional = false, bool $explodeAllLevels = false, ?int $parentProductId = null): array
    {
        if ($level > $maxLevel) {
            throw new BusinessException("BOM explosion exceeded maximum level ({$maxLevel}). Possible circular reference.");
        }

        // Prevent circular references
        if (in_array($bom->id, $visited)) {
            throw new BusinessException("Circular reference detected in BOM: {$bom->bom_number}");
        }

        $visited[] = $bom->id;
        $materials = [];

        $itemsQuery = $bom->items()->with('component.boms');
        if (!$includeOptional) {
            $itemsQuery->required();
        }

        foreach ($itemsQuery->get() as $item) {
            $requiredQty = $item->getRequiredQuantity($quantity / $bom->quantity);

            // Check if this component should be exploded
            $shouldExplode = false;
            $childBom = null;

            if ($item->is_phantom) {
                // Phantom items are always exploded if they have a BOM
                $childBom = Bom::where('product_id', $item->component_id)
                    ->where('is_default', true)
                    ->active()
                    ->first();
                $shouldExplode = $childBom !== null;
            } elseif ($explodeAllLevels) {
                // If explodeAllLevels is true, check if component has a BOM
                $childBom = Bom::where('product_id', $item->component_id)
                    ->where('is_default', true)
                    ->active()
                    ->first();
                $shouldExplode = $childBom !== null;
            }

            if ($shouldExplode && $childBom) {
                // Create parent entry (intermediate product with BOM)
                $parentEntry = $this->createMaterialEntry($item, $requiredQty, $level, $bom);
                $parentEntry['parent_product_id'] = $parentProductId;
                $parentEntry['has_children'] = true;
                $materials[] = $parentEntry;
                
                // Recursive explosion - children will have this product as parent
                $childMaterials = $this->explodeBomRecursive(
                    $childBom,
                    $requiredQty,
                    $level + 1,
                    $maxLevel,
                    $visited,
                    $includeOptional,
                    $explodeAllLevels,
                    $item->component_id // This product is the parent for children
                );
                $materials = array_merge($materials, $childMaterials);
            } else {
                // No BOM found or shouldn't explode, treat as raw material
                $material = $this->createMaterialEntry($item, $requiredQty, $level, $bom);
                $material['parent_product_id'] = $parentProductId;
                $material['has_children'] = false;
                $materials[] = $material;
            }
        }

        return $materials;
    }

    /**
     * Create material entry for explosion result
     */
    protected function createMaterialEntry(BomItem $item, float $quantity, int $level, Bom $bom): array
    {
        return [
            'product_id' => $item->component_id,
            'product_name' => $item->component->name,
            'product_sku' => $item->component->sku,
            'quantity' => round($quantity, 4),
            'uom_id' => $item->uom_id,
            'uom_code' => $item->uom->code,
            'level' => $level,
            'bom_id' => $bom->id,
            'bom_number' => $bom->bom_number,
            'bom_name' => $bom->name,
            'bom_item_id' => $item->id,
            'is_phantom' => $item->is_phantom,
            'is_optional' => $item->is_optional,
            'scrap_percentage' => $item->scrap_percentage,
        ];
    }

    /**
     * Validate no circular reference
     */
    protected function validateNoCircularReference(int $parentProductId, int $componentProductId, array $visited = []): void
    {
        if ($parentProductId === $componentProductId) {
            throw new BusinessException("A product cannot be a component of itself.");
        }

        if (in_array($componentProductId, $visited)) {
            throw new BusinessException("Circular reference detected in BOM structure.");
        }

        $visited[] = $componentProductId;

        // Check if component has a BOM that contains the parent
        $componentBoms = Bom::where('product_id', $componentProductId)->active()->get();

        foreach ($componentBoms as $bom) {
            foreach ($bom->items as $item) {
                $this->validateNoCircularReference($parentProductId, $item->component_id, $visited);
            }
        }
    }

    /**
     * Create BOM items in bulk
     */
    protected function createItems(Bom $bom, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $itemData['bom_id'] = $bom->id;
            $itemData['line_number'] = $itemData['line_number'] ?? ($index + 1);

            $this->validateNoCircularReference($bom->product_id, $itemData['component_id']);

            BomItem::create($itemData);
        }
    }

    /**
     * Generate BOM number
     */
    public function generateBomNumber(): string
    {
        $companyId = Auth::user()->company_id;

        $lastBom = Bom::withTrashed()
            ->where('company_id', $companyId)
            ->orderByRaw("CAST(SUBSTRING(bom_number FROM '[0-9]+') AS INTEGER) DESC")
            ->first();

        if ($lastBom && preg_match('/(\d+)/', $lastBom->bom_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'BOM-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get default BOM for a product
     */
    public function getDefaultBomForProduct(int $productId): ?Bom
    {
        return Bom::where('product_id', $productId)
            ->where('is_default', true)
            ->active()
            ->first();
    }
}
