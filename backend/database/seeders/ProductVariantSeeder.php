<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\VariantGeneratorService;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates variants for products using VariantGeneratorService
     */
    public function run(): void
    {
        $variantService = new VariantGeneratorService();

        // Get variant attributes
        $color = Attribute::where('name', 'color')->first();
        $size = Attribute::where('name', 'size')->first();
        $storage = Attribute::where('name', 'storage')->first();
        $ram = Attribute::where('name', 'ram')->first();

        if (!$color || !$storage) {
            $this->command->warn('Variant attributes not found! Please run AttributeSeeder first.');
            return;
        }

        // Category-based variant attribute mapping
        $categoryVariantMapping = [
            // Smartphones: Color + Storage
            'smartphones' => [
                'attributes' => [$color->id, $storage->id],
                'limit_values' => ['color' => 4, 'storage' => 4], // 4x4 = 16 variants per product
                'products_to_seed' => 30,
            ],
            'mobile-phones' => [
                'attributes' => [$color->id, $storage->id],
                'limit_values' => ['color' => 4, 'storage' => 4],
                'products_to_seed' => 30,
            ],
            // Tablets: Color + Storage
            'tablets' => [
                'attributes' => [$color->id, $storage->id],
                'limit_values' => ['color' => 4, 'storage' => 4],
                'products_to_seed' => 30,
            ],
            // Laptops: Storage + RAM
            'laptops' => [
                'attributes' => [$storage->id, $ram->id],
                'limit_values' => ['storage' => 4, 'ram' => 4],
                'products_to_seed' => 30,
            ],
            // Desktops: Storage + RAM
            'desktops' => [
                'attributes' => [$storage->id, $ram->id],
                'limit_values' => ['storage' => 4, 'ram' => 4],
                'products_to_seed' => 30,
            ],
            // Smartwatches: Color + Size
            'smartwatches' => [
                'attributes' => [$color->id, $size->id],
                'limit_values' => ['color' => 5, 'size' => 3],
                'products_to_seed' => 30,
            ],
            // Fitness Trackers: Color + Size
            'fitness-trackers' => [
                'attributes' => [$color->id, $size->id],
                'limit_values' => ['color' => 5, 'size' => 3],
                'products_to_seed' => 30,
            ],
            // Headphones: Color
            'headphones' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 6],
                'products_to_seed' => 30,
            ],
            // Speakers: Color
            'speakers' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 5],
                'products_to_seed' => 30,
            ],
            // Gaming Consoles: Color + Storage
            'gaming-consoles' => [
                'attributes' => [$color->id, $storage->id],
                'limit_values' => ['color' => 3, 'storage' => 3],
                'products_to_seed' => 30,
            ],
            // Gaming Accessories: Color
            'gaming-accessories' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 5],
                'products_to_seed' => 30,
            ],
            // Smart Speakers: Color
            'smart-speakers' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 4],
                'products_to_seed' => 30,
            ],
            // Computer Accessories: Color
            'computer-accessories' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 5],
                'products_to_seed' => 30,
            ],
            // Mobile Accessories: Color
            'mobile-accessories' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 6],
                'products_to_seed' => 30,
            ],
            // Accessories: Color
            'accessories' => [
                'attributes' => [$color->id],
                'limit_values' => ['color' => 5],
                'products_to_seed' => 30,
            ],
        ];

        $totalVariants = 0;

        foreach ($categoryVariantMapping as $categorySlug => $config) {
            $category = Category::where('slug', $categorySlug)->first();

            if (!$category) {
                $this->command->warn("Category not found: {$categorySlug}");
                continue;
            }

            // Get limited products from this category
            $products = Product::whereHas('categories', function ($q) use ($category) {
                    $q->where('categories.id', $category->id);
                })
                ->take($config['products_to_seed'])
                ->get();

            if ($products->isEmpty()) {
                $this->command->warn("No products found in category: {$categorySlug}");
                continue;
            }

            $this->command->info("Generating variants for {$category->name}...");

            foreach ($products as $product) {
                try {
                    // Limit attribute values for this generation
                    $this->limitAttributeValues($config['attributes'], $config['limit_values']);

                    $variants = $variantService->generateVariants($product, $config['attributes'], [
                        'base_price' => $product->price,
                        'base_stock' => rand(5, 50),
                        'clear_existing' => true,
                    ]);

                    $variantCount = count($variants);
                    $totalVariants += $variantCount;

                    $this->command->info("  - {$product->name}: {$variantCount} variants");

                    // Restore all attribute values
                    $this->restoreAttributeValues($config['attributes']);

                } catch (\Exception $e) {
                    $this->command->error("  - Error for {$product->name}: {$e->getMessage()}");
                    // Restore values even on error
                    $this->restoreAttributeValues($config['attributes']);
                }
            }
        }

        $this->command->info("Variant generation completed! Total: {$totalVariants} variants");
    }

    /**
     * Temporarily limit attribute values for seeding
     */
    private function limitAttributeValues(array $attributeIds, array $limits): void
    {
        foreach ($attributeIds as $attributeId) {
            $attribute = Attribute::find($attributeId);
            if (!$attribute) continue;

            $limitKey = $attribute->name;
            if (!isset($limits[$limitKey])) continue;

            $limit = $limits[$limitKey];

            // Deactivate values beyond the limit
            $attribute->values()
                ->orderBy('order')
                ->skip($limit)
                ->take(100)
                ->update(['is_active' => false]);
        }
    }

    /**
     * Restore all attribute values to active
     */
    private function restoreAttributeValues(array $attributeIds): void
    {
        foreach ($attributeIds as $attributeId) {
            $attribute = Attribute::find($attributeId);
            if (!$attribute) continue;

            $attribute->values()->update(['is_active' => true]);
        }
    }
}
