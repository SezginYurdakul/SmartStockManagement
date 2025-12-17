<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class VariantGeneratorService
{
    /**
     * Generate product variants from attributes
     *
     * @param Product $product
     * @param array $attributeIds Array of attribute IDs to use for variant generation
     * @param array $options Configuration options
     * @return array Generated variants
     */
    public function generateVariants(Product $product, array $attributeIds, array $options = []): array
    {
        // Get attributes with their values
        $attributes = Attribute::with('activeValues')
            ->whereIn('id', $attributeIds)
            ->variantAttributes() // Only variant-capable attributes
            ->get();

        if ($attributes->isEmpty()) {
            throw new \Exception('No variant attributes found');
        }

        // Get selected value IDs filter (if provided)
        $selectedValueIds = $options['selected_value_ids'] ?? [];

        // Prepare attribute values for cartesian product
        $attributeValueSets = [];
        foreach ($attributes as $attribute) {
            if ($attribute->activeValues->isEmpty()) {
                throw new \Exception("Attribute '{$attribute->display_name}' has no values");
            }

            // Check if specific value IDs are selected for this attribute
            $attributeIdStr = (string) $attribute->id;
            if (!empty($selectedValueIds) && isset($selectedValueIds[$attributeIdStr])) {
                // Use only the selected values by ID
                $requestedIds = $selectedValueIds[$attributeIdStr];

                // Get values that match the requested IDs and belong to this attribute
                $filteredValues = $attribute->activeValues
                    ->whereIn('id', $requestedIds)
                    ->pluck('value')
                    ->toArray();

                if (empty($filteredValues)) {
                    throw new \Exception("None of the selected value IDs exist for attribute '{$attribute->display_name}'");
                }

                $attributeValueSets[$attribute->id] = array_values($filteredValues);
            } else {
                // Use all active values
                $attributeValueSets[$attribute->id] = $attribute->activeValues->pluck('value')->toArray();
            }
        }

        // Generate all combinations (Cartesian product)
        $combinations = $this->cartesianProduct($attributeValueSets);

        // Default options - null means "to be set later via update"
        $basePrice = $options['base_price'] ?? null;
        $baseStock = $options['base_stock'] ?? null;
        $priceIncrementsById = $options['price_increments'] ?? [];
        $clearExisting = $options['clear_existing'] ?? false;

        // Convert price_increments from value_id => increment to value_string => increment
        $priceIncrements = [];
        if (!empty($priceIncrementsById)) {
            $valueIds = array_keys($priceIncrementsById);
            $values = AttributeValue::whereIn('id', $valueIds)->pluck('value', 'id');
            foreach ($priceIncrementsById as $valueId => $increment) {
                if (isset($values[$valueId])) {
                    $priceIncrements[$values[$valueId]] = $increment;
                }
            }
        }

        // Clear existing variants if requested
        if ($clearExisting) {
            $product->variants()->delete();
        }

        $generatedVariants = [];

        DB::transaction(function () use ($product, $combinations, $attributes, $basePrice, $baseStock, $priceIncrements, &$generatedVariants) {
            foreach ($combinations as $combination) {
                // Build variant name and attributes
                $variantParts = [];
                $variantAttributes = [];
                $variantPrice = $basePrice;

                foreach ($combination as $attributeId => $value) {
                    $attribute = $attributes->firstWhere('id', $attributeId);
                    $variantParts[] = $value;
                    // Use lowercase key for consistency with existing variants
                    $variantAttributes[strtolower($attribute->name)] = $value;

                    // Apply price increment if specified and base price is set
                    if ($basePrice !== null && isset($priceIncrements[$value])) {
                        $variantPrice = ($variantPrice ?? 0) + $priceIncrements[$value];
                    }
                }

                // Check if variant with same attributes already exists (including soft-deleted)
                $existingVariant = $this->findExistingVariant($product, $variantAttributes);
                if ($existingVariant) {
                    // If soft-deleted, restore it with updated values
                    if ($existingVariant->trashed()) {
                        $existingVariant->restore();
                        $existingVariant->update([
                            'price' => $variantPrice,
                            'stock' => $baseStock,
                            'is_active' => $variantPrice !== null && $baseStock !== null,
                        ]);
                        $generatedVariants[] = $existingVariant->fresh();
                    }
                    // Skip if already active
                    continue;
                }

                $variantName = implode(' - ', $variantParts);
                $variantSku = $this->generateVariantSku($product, $variantParts);

                // Create variant - price and stock can be null (to be set later)
                // Auto-activate if both price and stock are provided
                $isComplete = $variantPrice !== null && $baseStock !== null;

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantName,
                    'sku' => $variantSku,
                    'price' => $variantPrice,
                    'stock' => $baseStock,
                    'attributes' => $variantAttributes,
                    'is_active' => $isComplete,
                ]);

                $generatedVariants[] = $variant;
            }
        });

        return $generatedVariants;
    }

    /**
     * Generate cartesian product of arrays
     *
     * @param array $sets Associative array of attribute_id => [values]
     * @return array Array of combinations
     */
    private function cartesianProduct(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $attributeId => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $value) {
                    $newProduct = $product;
                    $newProduct[$attributeId] = $value;
                    $append[] = $newProduct;
                }
            }

            $result = $append;
        }

        return $result;
    }

    /**
     * Generate unique SKU for variant
     *
     * @param Product $product
     * @param array $variantParts
     * @return string
     */
    private function generateVariantSku(Product $product, array $variantParts): string
    {
        $baseSku = $product->sku;
        $suffix = strtoupper(implode('-', array_map(function ($part) {
            return Str::slug($part);
        }, $variantParts)));

        $sku = "{$baseSku}-{$suffix}";

        // Ensure uniqueness (including soft-deleted variants)
        $counter = 1;
        $originalSku = $sku;

        while (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = "{$originalSku}-{$counter}";
            $counter++;
        }

        return $sku;
    }

    /**
     * Find existing variant with same attributes
     *
     * @param Product $product
     * @param array $attributes
     * @return ProductVariant|null
     */
    private function findExistingVariant(Product $product, array $attributes): ?ProductVariant
    {
        // Get all variants for this product (including soft-deleted)
        $variants = $product->variants()->withTrashed()->get();

        // Normalize input attributes (lowercase keys)
        $normalizedAttrs = array_change_key_case($attributes, CASE_LOWER);
        ksort($normalizedAttrs);

        foreach ($variants as $variant) {
            $variantAttrs = $variant->attributes ?? [];

            // Normalize existing variant attributes (lowercase keys)
            $normalizedVariantAttrs = array_change_key_case($variantAttrs, CASE_LOWER);
            ksort($normalizedVariantAttrs);

            // Compare normalized attributes
            if ($normalizedAttrs == $normalizedVariantAttrs) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Clear all variants for a product
     *
     * @param Product $product
     * @return int Number of deleted variants
     */
    public function clearVariants(Product $product): int
    {
        return $product->variants()->delete();
    }
}
