<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UnitOfMeasureService
{
    /**
     * Get a single unit of measure with product count
     */
    public function getUnitOfMeasure(UnitOfMeasure $uom): UnitOfMeasure
    {
        return $uom->load('baseUnit')->loadCount('products');
    }

    /**
     * Get paginated units of measure with optional filters
     */
    public function getUnitsOfMeasure(array $filters = [], int $perPage = 15)
    {
        $query = UnitOfMeasure::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by type
        if (!empty($filters['uom_type'])) {
            $query->where('uom_type', $filters['uom_type']);
        }

        // Filter base units only
        if (isset($filters['base_units_only']) && $filters['base_units_only']) {
            $query->whereNull('base_unit_id');
        }

        return $query->with('baseUnit')
            ->withCount('products')
            ->orderBy('uom_type')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Get all units grouped by type (for dropdowns)
     */
    public function getGroupedByType()
    {
        return UnitOfMeasure::active()
            ->orderBy('name')
            ->get()
            ->groupBy('uom_type');
    }

    /**
     * Create a new unit of measure
     */
    public function create(array $data): UnitOfMeasure
    {
        Log::info('Creating new unit of measure', ['code' => $data['code']]);

        // Validate conversion factor if base_unit_id is provided
        if (!empty($data['base_unit_id']) && empty($data['conversion_factor'])) {
            throw new BusinessException("Conversion factor is required when a base unit is specified");
        }

        DB::beginTransaction();

        try {
            $uom = UnitOfMeasure::create($data);

            DB::commit();

            Log::info('Unit of measure created successfully', [
                'uom_id' => $uom->id,
                'code' => $uom->code,
            ]);

            return $uom->load('baseUnit');

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create unit of measure', [
                'code' => $data['code'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to create unit of measure: {$e->getMessage()}");
        }
    }

    /**
     * Update a unit of measure
     */
    public function update(UnitOfMeasure $uom, array $data): UnitOfMeasure
    {
        Log::info('Updating unit of measure', [
            'uom_id' => $uom->id,
            'changes' => array_keys($data),
        ]);

        // Prevent circular reference
        if (!empty($data['base_unit_id']) && $data['base_unit_id'] == $uom->id) {
            throw new BusinessException("A unit cannot be its own base unit");
        }

        // Validate conversion factor
        if (!empty($data['base_unit_id']) && empty($data['conversion_factor']) && empty($uom->conversion_factor)) {
            throw new BusinessException("Conversion factor is required when a base unit is specified");
        }

        DB::beginTransaction();

        try {
            $uom->update($data);

            DB::commit();

            Log::info('Unit of measure updated successfully', [
                'uom_id' => $uom->id,
            ]);

            return $uom->fresh()->load('baseUnit');

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update unit of measure', [
                'uom_id' => $uom->id,
                'error' => $e->getMessage(),
            ]);

            throw new BusinessException("Failed to update unit of measure: {$e->getMessage()}");
        }
    }

    /**
     * Delete a unit of measure
     */
    public function delete(UnitOfMeasure $uom): bool
    {
        // Check if unit has products
        if ($uom->products()->count() > 0) {
            throw new BusinessException("Cannot delete unit of measure with existing products");
        }

        // Check if unit is used as base for other units
        if ($uom->derivedUnits()->count() > 0) {
            throw new BusinessException("Cannot delete unit of measure that is used as base for other units");
        }

        Log::info('Deleting unit of measure', [
            'uom_id' => $uom->id,
            'code' => $uom->code,
        ]);

        return $uom->delete();
    }

    /**
     * Toggle unit active status
     */
    public function toggleActive(UnitOfMeasure $uom): UnitOfMeasure
    {
        $uom->update(['is_active' => !$uom->is_active]);

        Log::info('Unit of measure active status toggled', [
            'uom_id' => $uom->id,
            'is_active' => $uom->is_active,
        ]);

        return $uom;
    }

    /**
     * Convert quantity between units
     */
    public function convert(float $quantity, UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): ?float
    {
        return $fromUnit->convertTo($quantity, $toUnit);
    }
}
