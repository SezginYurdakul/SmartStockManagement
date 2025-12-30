<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Routing;
use App\Models\RoutingOperation;
use App\Enums\RoutingStatus;
use App\Exceptions\BusinessException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoutingService
{
    /**
     * Get paginated routings with filters
     */
    public function getRoutings(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Routing::with(['product:id,name,sku', 'creator:id,first_name,last_name'])
            ->withCount('operations');

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

        // Default only
        if (!empty($filters['is_default'])) {
            $query->default();
        }

        // Active only
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        return $query->orderBy('routing_number')->paginate($perPage);
    }

    /**
     * Get all active routings for dropdowns
     */
    public function getActiveRoutings(): Collection
    {
        return Routing::active()
            ->with('product:id,name,sku')
            ->orderBy('routing_number')
            ->get(['id', 'routing_number', 'name', 'product_id', 'version']);
    }

    /**
     * Get routings for a specific product
     */
    public function getRoutingsForProduct(int $productId): Collection
    {
        return Routing::forProduct($productId)
            ->withCount('operations')
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Get routing with full relationships
     */
    public function getRouting(Routing $routing): Routing
    {
        return $routing->load([
            'product:id,name,sku',
            'creator:id,first_name,last_name',
            'operations.workCenter:id,code,name,cost_per_hour',
            'operations.subcontractor:id,name',
        ]);
    }

    /**
     * Create a new routing
     */
    public function create(array $data): Routing
    {
        Log::info('Creating routing', [
            'product_id' => $data['product_id'] ?? null,
            'routing_number' => $data['routing_number'] ?? null,
        ]);

        // Validate product can have routing
        $product = Product::with('productType')->findOrFail($data['product_id']);
        if (!$product->isManufacturable()) {
            throw new BusinessException(
                "Product '{$product->name}' cannot have a routing. Product type must allow manufacturing."
            );
        }

        return DB::transaction(function () use ($data) {
            $data['company_id'] = Auth::user()->company_id;
            $data['created_by'] = Auth::id();

            // Generate routing number if not provided
            if (empty($data['routing_number'])) {
                $data['routing_number'] = $this->generateRoutingNumber();
            }

            // If this is the first routing for the product, make it default
            $existingCount = Routing::where('product_id', $data['product_id'])
                ->where('company_id', $data['company_id'])
                ->count();

            if ($existingCount === 0) {
                $data['is_default'] = true;
            }

            $routing = Routing::create($data);

            // Create operations if provided
            if (!empty($data['operations'])) {
                $this->createOperations($routing, $data['operations']);
            }

            Log::info('Routing created', ['id' => $routing->id, 'routing_number' => $routing->routing_number]);

            return $routing->fresh(['operations']);
        });
    }

    /**
     * Update routing
     */
    public function update(Routing $routing, array $data): Routing
    {
        if (!$routing->canEdit()) {
            throw new BusinessException("Routing cannot be edited in {$routing->status->label()} status.");
        }

        Log::info('Updating routing', [
            'id' => $routing->id,
            'changes' => array_keys($data),
        ]);

        $routing->update($data);

        return $routing->fresh();
    }

    /**
     * Delete routing
     */
    public function delete(Routing $routing): bool
    {
        if ($routing->workOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            throw new BusinessException("Cannot delete routing with active work orders.");
        }

        Log::info('Deleting routing', ['id' => $routing->id]);

        return $routing->delete();
    }

    /**
     * Add operation to routing
     */
    public function addOperation(Routing $routing, array $data): RoutingOperation
    {
        if (!$routing->canEdit()) {
            throw new BusinessException("Cannot add operations to routing in {$routing->status->label()} status.");
        }

        // Get next operation number
        $nextOpNumber = ($routing->operations()->max('operation_number') ?? 0) + 10;
        $data['operation_number'] = $data['operation_number'] ?? $nextOpNumber;
        $data['routing_id'] = $routing->id;

        Log::info('Adding routing operation', [
            'routing_id' => $routing->id,
            'operation_number' => $data['operation_number'],
        ]);

        return RoutingOperation::create($data);
    }

    /**
     * Update routing operation
     */
    public function updateOperation(Routing $routing, int $operationId, array $data): RoutingOperation
    {
        if (!$routing->canEdit()) {
            throw new BusinessException("Cannot update operations in routing in {$routing->status->label()} status.");
        }

        $operation = $routing->operations()->findOrFail($operationId);

        Log::info('Updating routing operation', ['routing_id' => $routing->id, 'operation_id' => $operationId]);

        $operation->update($data);

        return $operation->fresh();
    }

    /**
     * Remove operation from routing
     */
    public function removeOperation(Routing $routing, int $operationId): bool
    {
        if (!$routing->canEdit()) {
            throw new BusinessException("Cannot remove operations from routing in {$routing->status->label()} status.");
        }

        $operation = $routing->operations()->findOrFail($operationId);

        Log::info('Removing routing operation', ['routing_id' => $routing->id, 'operation_id' => $operationId]);

        return $operation->delete();
    }

    /**
     * Reorder operations
     */
    public function reorderOperations(Routing $routing, array $operationIds): void
    {
        if (!$routing->canEdit()) {
            throw new BusinessException("Cannot reorder operations in routing in {$routing->status->label()} status.");
        }

        Log::info('Reordering routing operations', ['routing_id' => $routing->id]);

        DB::transaction(function () use ($routing, $operationIds) {
            foreach ($operationIds as $index => $operationId) {
                $routing->operations()
                    ->where('id', $operationId)
                    ->update(['operation_number' => ($index + 1) * 10]);
            }
        });
    }

    /**
     * Activate routing
     */
    public function activate(Routing $routing): Routing
    {
        if (!$routing->status->canTransitionTo(RoutingStatus::ACTIVE)) {
            throw new BusinessException("Cannot activate routing from {$routing->status->label()} status.");
        }

        if ($routing->operations()->count() === 0) {
            throw new BusinessException("Cannot activate routing without operations.");
        }

        Log::info('Activating routing', ['id' => $routing->id]);

        $routing->update(['status' => RoutingStatus::ACTIVE]);

        return $routing->fresh();
    }

    /**
     * Mark routing as obsolete
     */
    public function obsolete(Routing $routing): Routing
    {
        if (!$routing->status->canTransitionTo(RoutingStatus::OBSOLETE)) {
            throw new BusinessException("Cannot mark routing as obsolete from {$routing->status->label()} status.");
        }

        Log::info('Marking routing as obsolete', ['id' => $routing->id]);

        $routing->update([
            'status' => RoutingStatus::OBSOLETE,
            'is_default' => false,
        ]);

        return $routing->fresh();
    }

    /**
     * Set routing as default for product
     */
    public function setAsDefault(Routing $routing): Routing
    {
        if ($routing->status !== RoutingStatus::ACTIVE) {
            throw new BusinessException("Only active routings can be set as default.");
        }

        Log::info('Setting routing as default', ['id' => $routing->id, 'product_id' => $routing->product_id]);

        DB::transaction(function () use ($routing) {
            // Remove default from other routings of same product
            Routing::where('product_id', $routing->product_id)
                ->where('id', '!=', $routing->id)
                ->update(['is_default' => false]);

            $routing->update(['is_default' => true]);
        });

        return $routing->fresh();
    }

    /**
     * Copy routing to new version
     */
    public function copy(Routing $routing, ?string $newName = null): Routing
    {
        Log::info('Copying routing', ['source_id' => $routing->id]);

        return DB::transaction(function () use ($routing, $newName) {
            // Get next version number
            $nextVersion = Routing::where('product_id', $routing->product_id)
                ->where('company_id', $routing->company_id)
                ->max('version') + 1;

            // Create new routing
            $newRouting = Routing::create([
                'company_id' => $routing->company_id,
                'product_id' => $routing->product_id,
                'routing_number' => $this->generateRoutingNumber(),
                'version' => $nextVersion,
                'name' => $newName ?? "{$routing->name} (Copy)",
                'description' => $routing->description,
                'status' => RoutingStatus::DRAFT,
                'is_default' => false,
                'notes' => $routing->notes,
                'created_by' => Auth::id(),
            ]);

            // Copy operations
            foreach ($routing->operations as $operation) {
                RoutingOperation::create([
                    'routing_id' => $newRouting->id,
                    'work_center_id' => $operation->work_center_id,
                    'operation_number' => $operation->operation_number,
                    'name' => $operation->name,
                    'description' => $operation->description,
                    'setup_time' => $operation->setup_time,
                    'run_time_per_unit' => $operation->run_time_per_unit,
                    'queue_time' => $operation->queue_time,
                    'move_time' => $operation->move_time,
                    'is_subcontracted' => $operation->is_subcontracted,
                    'subcontractor_id' => $operation->subcontractor_id,
                    'subcontract_cost' => $operation->subcontract_cost,
                    'instructions' => $operation->instructions,
                    'settings' => $operation->settings,
                ]);
            }

            Log::info('Routing copied', ['source_id' => $routing->id, 'new_id' => $newRouting->id]);

            return $newRouting->fresh(['operations']);
        });
    }

    /**
     * Calculate total lead time for a quantity
     */
    public function calculateLeadTime(Routing $routing, float $quantity): array
    {
        $totalSetup = 0;
        $totalRun = 0;
        $totalQueue = 0;
        $totalMove = 0;

        foreach ($routing->operations as $op) {
            $totalSetup += $op->setup_time;
            $totalRun += $op->run_time_per_unit * $quantity;
            $totalQueue += $op->queue_time;
            $totalMove += $op->move_time;
        }

        $totalMinutes = $totalSetup + $totalRun + $totalQueue + $totalMove;

        return [
            'setup_time' => round($totalSetup, 2),
            'run_time' => round($totalRun, 2),
            'queue_time' => round($totalQueue, 2),
            'move_time' => round($totalMove, 2),
            'total_minutes' => round($totalMinutes, 2),
            'total_hours' => round($totalMinutes / 60, 2),
            'total_days' => round($totalMinutes / 60 / 8, 2), // Assuming 8-hour workday
        ];
    }

    /**
     * Create routing operations in bulk
     */
    protected function createOperations(Routing $routing, array $operations): void
    {
        foreach ($operations as $index => $opData) {
            $opData['routing_id'] = $routing->id;
            $opData['operation_number'] = $opData['operation_number'] ?? (($index + 1) * 10);

            RoutingOperation::create($opData);
        }
    }

    /**
     * Generate routing number
     */
    public function generateRoutingNumber(): string
    {
        $companyId = Auth::user()->company_id;

        $lastRouting = Routing::withTrashed()
            ->where('company_id', $companyId)
            ->orderByRaw("CAST(SUBSTRING(routing_number FROM '[0-9]+') AS INTEGER) DESC")
            ->first();

        if ($lastRouting && preg_match('/(\d+)/', $lastRouting->routing_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'RTG-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get default routing for a product
     */
    public function getDefaultRoutingForProduct(int $productId): ?Routing
    {
        return Routing::where('product_id', $productId)
            ->where('is_default', true)
            ->active()
            ->first();
    }
}
