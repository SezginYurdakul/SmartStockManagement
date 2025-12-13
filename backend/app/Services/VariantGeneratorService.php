<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Attribute;
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

        // Prepare attribute values for cartesian product
        $attributeValueSets = [];
        foreach ($attributes as $attribute) {
            if ($attribute->activeValues->isEmpty()) {
                throw new \Exception("Attribute '{$attribute->display_name}' has no values");
            }

            $attributeValueSets[$attribute->id] = $attribute->activeValues->pluck('value')->toArray();
        }

        // Generate all combinations (Cartesian product)
        $combinations = $this->cartesianProduct($attributeValueSets);

        // Default options - null means "to be set later via update"
        $basePrice = $options['base_price'] ?? null;
        $baseStock = $options['base_stock'] ?? null;
        $priceIncrements = $options['price_increments'] ?? [];
        $clearExisting = $options['clear_existing'] ?? false;

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
                    $variantAttributes[$attribute->name] = $value;

                    // Apply price increment if specified and base price is set
                    if ($basePrice !== null && isset($priceIncrements[$value])) {
                        $variantPrice = ($variantPrice ?? 0) + $priceIncrements[$value];
                    }
                }

                // Check if variant with same attributes already exists
                $existingVariant = $this->findExistingVariant($product, $variantAttributes);
                if ($existingVariant) {
                    // Skip duplicate variant
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

        // Ensure uniqueness
        $counter = 1;
        $originalSku = $sku;

        while (ProductVariant::where('sku', $sku)->exists()) {
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
        // Get all variants for this product
        $variants = $product->variants()->get();

        foreach ($variants as $variant) {
            $variantAttrs = $variant->attributes ?? [];

            // Sort both arrays by key for comparison
            ksort($attributes);
            ksort($variantAttrs);

            // Compare attributes
            if ($attributes == $variantAttrs) {
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
