<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductUomConversionService
{
    /**
     * Get all conversions for a product
     */
    public function getConversionsForProduct(Product $product): array
    {
        $conversions = $product->uomConversions()
            ->with(['fromUom', 'toUom'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'conversions' => $conversions,
            'base_unit' => $product->unitOfMeasure,
            'available_units' => $product->getAvailableUnits(),
        ];
    }

    /**
     * Create a new product-specific conversion
     */
    public function create(Product $product, array $data): ProductUomConversion
    {
        return DB::transaction(function () use ($product, $data) {
            // If this is set as default, unset other defaults for same from_uom
            if ($data['is_default'] ?? false) {
                $product->uomConversions()
                    ->where('from_uom_id', $data['from_uom_id'])
                    ->update(['is_default' => false]);
            }

            return ProductUomConversion::create([
                'company_id' => Auth::user()->company_id,
                'product_id' => $product->id,
                'from_uom_id' => $data['from_uom_id'],
                'to_uom_id' => $data['to_uom_id'],
                'conversion_factor' => $data['conversion_factor'],
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update an existing conversion
     */
    public function update(ProductUomConversion $conversion, array $data): ProductUomConversion
    {
        return DB::transaction(function () use ($conversion, $data) {
            // If this is set as default, unset other defaults for same from_uom
            if (($data['is_default'] ?? false) && !$conversion->is_default) {
                ProductUomConversion::where('product_id', $conversion->product_id)
                    ->where('from_uom_id', $data['from_uom_id'] ?? $conversion->from_uom_id)
                    ->where('id', '!=', $conversion->id)
                    ->update(['is_default' => false]);
            }

            $conversion->update($data);

            return $conversion->fresh(['fromUom', 'toUom']);
        });
    }

    /**
     * Delete a conversion
     */
    public function delete(ProductUomConversion $conversion): bool
    {
        return $conversion->delete();
    }

    /**
     * Toggle conversion active status
     */
    public function toggleActive(ProductUomConversion $conversion): ProductUomConversion
    {
        $conversion->update(['is_active' => !$conversion->is_active]);

        return $conversion->fresh();
    }

    /**
     * Convert quantity for a specific product
     *
     * @param Product $product
     * @param float $quantity
     * @param int $fromUomId
     * @param int $toUomId
     * @return array Result with converted quantity and conversion info
     */
    public function convert(Product $product, float $quantity, int $fromUomId, int $toUomId): array
    {
        $fromUom = UnitOfMeasure::findOrFail($fromUomId);
        $toUom = UnitOfMeasure::findOrFail($toUomId);

        // Try product-specific conversion first
        $productConversion = $product->uomConversions()
            ->active()
            ->where(function ($query) use ($fromUomId, $toUomId) {
                $query->where(function ($q) use ($fromUomId, $toUomId) {
                    $q->where('from_uom_id', $fromUomId)
                        ->where('to_uom_id', $toUomId);
                })->orWhere(function ($q) use ($fromUomId, $toUomId) {
                    $q->where('from_uom_id', $toUomId)
                        ->where('to_uom_id', $fromUomId);
                });
            })
            ->first();

        if ($productConversion) {
            $isReverse = $productConversion->from_uom_id === $toUomId;
            $result = $isReverse
                ? $productConversion->reverseConvert($quantity)
                : $productConversion->convert($quantity);

            return [
                'success' => true,
                'from' => [
                    'quantity' => $quantity,
                    'unit' => $fromUom->code,
                    'unit_name' => $fromUom->name,
                ],
                'to' => [
                    'quantity' => $result,
                    'unit' => $toUom->code,
                    'unit_name' => $toUom->name,
                    'formatted' => $toUom->formatQuantity($result) . ' ' . $toUom->code,
                ],
                'conversion_type' => 'product_specific',
                'conversion_display' => $productConversion->getDisplayString(),
            ];
        }

        // Fall back to standard conversion
        $result = $fromUom->convertTo($quantity, $toUom);

        if ($result === null) {
            return [
                'success' => false,
                'error' => 'Cannot convert between these units. They may be of different types or missing conversion factors.',
            ];
        }

        return [
            'success' => true,
            'from' => [
                'quantity' => $quantity,
                'unit' => $fromUom->code,
                'unit_name' => $fromUom->name,
            ],
            'to' => [
                'quantity' => $result,
                'unit' => $toUom->code,
                'unit_name' => $toUom->name,
                'formatted' => $toUom->formatQuantity($result) . ' ' . $toUom->code,
            ],
            'conversion_type' => 'standard',
        ];
    }

    /**
     * Bulk create conversions for a product
     */
    public function bulkCreate(Product $product, array $conversions): array
    {
        $created = [];

        DB::transaction(function () use ($product, $conversions, &$created) {
            foreach ($conversions as $data) {
                $created[] = $this->create($product, $data);
            }
        });

        return $created;
    }

    /**
     * Copy conversions from one product to another
     */
    public function copyFromProduct(Product $sourceProduct, Product $targetProduct): array
    {
        $copied = [];

        DB::transaction(function () use ($sourceProduct, $targetProduct, &$copied) {
            $conversions = $sourceProduct->uomConversions()->get();

            foreach ($conversions as $conversion) {
                $copied[] = ProductUomConversion::create([
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $targetProduct->id,
                    'from_uom_id' => $conversion->from_uom_id,
                    'to_uom_id' => $conversion->to_uom_id,
                    'conversion_factor' => $conversion->conversion_factor,
                    'is_default' => $conversion->is_default,
                    'is_active' => $conversion->is_active,
                ]);
            }
        });

        return $copied;
    }
}
