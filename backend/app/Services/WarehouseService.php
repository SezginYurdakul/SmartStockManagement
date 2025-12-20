<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class WarehouseService
{
    /**
     * Get a single warehouse with stock summary
     */
    public function getWarehouse(Warehouse $warehouse): Warehouse
    {
        return $warehouse->loadCount('stocks');
    }

    /**
     * Get paginated warehouses with optional filters
     */
    public function getWarehouses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Warehouse::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by warehouse type
        if (!empty($filters['warehouse_type'])) {
            $query->where('warehouse_type', $filters['warehouse_type']);
        }

        // Filter by city
        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        // Filter by country
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        return $query->withCount('stocks')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Get all active warehouses (for dropdowns)
     */
    public function getActiveWarehouses()
    {
        return Warehouse::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'warehouse_type', 'is_default']);
    }

    /**
     * Get the default warehouse
     */
    public function getDefaultWarehouse(): ?Warehouse
    {
        return Warehouse::default()->first();
    }

    /**
     * Create a new warehouse
     */
    public function create(array $data): Warehouse
    {
        Log::info('Creating new warehouse', ['name' => $data['name']]);

        DB::beginTransaction();

        try {
            // If this is set as default, unset other defaults
            if (!empty($data['is_default']) && $data['is_default']) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }

            // Set created_by
            $data['created_by'] = Auth::id();

            $warehouse = Warehouse::create($data);

            DB::commit();

            Log::info('Warehouse created successfully', [
                'warehouse_id' => $warehouse->id,
                'code' => $warehouse->code,
            ]);

            return $warehouse;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create warehouse', [
                'name' => $data['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to create warehouse: {$e->getMessage()}");
        }
    }

    /**
     * Update a warehouse
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        Log::info('Updating warehouse', [
            'warehouse_id' => $warehouse->id,
            'changes' => array_keys($data),
        ]);

        DB::beginTransaction();

        try {
            // If this is set as default, unset other defaults
            if (!empty($data['is_default']) && $data['is_default'] && !$warehouse->is_default) {
                Warehouse::where('id', '!=', $warehouse->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $warehouse->update($data);

            DB::commit();

            Log::info('Warehouse updated successfully', [
                'warehouse_id' => $warehouse->id,
            ]);

            return $warehouse->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update warehouse', [
                'warehouse_id' => $warehouse->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to update warehouse: {$e->getMessage()}");
        }
    }

    /**
     * Delete a warehouse
     */
    public function delete(Warehouse $warehouse): bool
    {
        // Check if warehouse has stock
        if ($warehouse->stocks()->count() > 0) {
            throw new BusinessException(
                "Cannot delete warehouse with existing stock. Please transfer or adjust the stock first."
            );
        }

        // Check if warehouse has movements
        if ($warehouse->stockMovements()->count() > 0) {
            throw new BusinessException(
                "Cannot delete warehouse with stock movement history. Consider deactivating it instead."
            );
        }

        // Check if it's the default warehouse
        if ($warehouse->is_default) {
            throw new BusinessException(
                "Cannot delete the default warehouse. Please set another warehouse as default first."
            );
        }

        Log::info('Deleting warehouse', [
            'warehouse_id' => $warehouse->id,
            'code' => $warehouse->code,
        ]);

        return $warehouse->delete();
    }

    /**
     * Toggle warehouse active status
     */
    public function toggleActive(Warehouse $warehouse): Warehouse
    {
        // If deactivating, check if it has available stock
        if ($warehouse->is_active) {
            $hasAvailableStock = $warehouse->stocks()
                ->where('quantity_available', '>', 0)
                ->exists();

            if ($hasAvailableStock) {
                throw new BusinessException(
                    "Cannot deactivate warehouse with available stock. Please transfer the stock first."
                );
            }
        }

        $warehouse->update(['is_active' => !$warehouse->is_active]);

        Log::info('Warehouse active status toggled', [
            'warehouse_id' => $warehouse->id,
            'is_active' => $warehouse->is_active,
        ]);

        return $warehouse;
    }

    /**
     * Set a warehouse as default
     */
    public function setAsDefault(Warehouse $warehouse): Warehouse
    {
        if (!$warehouse->is_active) {
            throw new BusinessException("Cannot set an inactive warehouse as default.");
        }

        DB::beginTransaction();

        try {
            // Unset current default
            Warehouse::where('is_default', true)->update(['is_default' => false]);

            // Set new default
            $warehouse->update(['is_default' => true]);

            DB::commit();

            Log::info('Warehouse set as default', [
                'warehouse_id' => $warehouse->id,
            ]);

            return $warehouse->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw new BusinessException("Failed to set default warehouse: {$e->getMessage()}");
        }
    }

    /**
     * Get warehouse stock summary
     */
    public function getStockSummary(Warehouse $warehouse): array
    {
        $stocks = $warehouse->stocks()
            ->with('product:id,name,sku')
            ->selectRaw('
                product_id,
                SUM(quantity_on_hand) as total_on_hand,
                SUM(quantity_reserved) as total_reserved,
                SUM(quantity_available) as total_available,
                SUM(total_value) as total_value
            ')
            ->groupBy('product_id')
            ->get();

        return [
            'total_products' => $stocks->count(),
            'total_quantity_on_hand' => $stocks->sum('total_on_hand'),
            'total_quantity_reserved' => $stocks->sum('total_reserved'),
            'total_quantity_available' => $stocks->sum('total_available'),
            'total_value' => $stocks->sum('total_value'),
            'products' => $stocks,
        ];
    }
}
