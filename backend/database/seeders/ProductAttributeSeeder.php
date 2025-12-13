<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Assigns base product attributes (Brand, Warranty, Material) to products
     */
    public function run(): void
    {
        // Get non-variant attributes (base product attributes)
        $brand = Attribute::where('name', 'brand')->first();
        $warranty = Attribute::where('name', 'warranty')->first();
        $material = Attribute::where('name', 'material')->first();

        if (!$brand || !$warranty || !$material) {
            $this->command->warn('Attributes not found! Please run AttributeSeeder first.');
            return;
        }

        // Get brand values for mapping
        $brandValues = $brand->values->pluck('value')->toArray();
        $warrantyValues = $warranty->values->pluck('value')->toArray();
        $materialValues = $material->values->pluck('value')->toArray();

        // Brand name mapping (from product meta_data or name)
        $brandMapping = [
            'Apple' => 'Apple',
            'Samsung' => 'Samsung',
            'Huawei' => 'Huawei',
            'Xiaomi' => 'Xiaomi',
            'Sony' => 'Sony',
            'LG' => 'LG',
            'Asus' => 'Asus',
            'ASUS' => 'Asus',
            'Lenovo' => 'Lenovo',
            'Dell' => 'Dell',
            'HP' => 'HP',
            'Microsoft' => 'Apple', // Map to Apple as fallback
            'Google' => 'Samsung', // Map to Samsung as fallback
            'Canon' => 'Sony', // Map to Sony as fallback
            'Nikon' => 'Sony',
            'Bose' => 'Sony',
            'JBL' => 'Sony',
            'Logitech' => 'Asus',
            'Razer' => 'Asus',
        ];

        // Category-based warranty mapping
        $categoryWarrantyMapping = [
            'electronics' => '2 Years',
            'tablets' => '1 Year',
            'accessories' => '6 Months',
            'laptops' => '2 Years',
            'desktops' => '2 Years',
            'computer-accessories' => '1 Year',
            'smartphones' => '1 Year',
            'mobile-accessories' => '6 Months',
            'cameras-photography' => '2 Years',
            'dslr-cameras' => '2 Years',
            'mirrorless-cameras' => '2 Years',
            'lenses' => '1 Year',
            'audio-headphones' => '1 Year',
            'headphones' => '1 Year',
            'speakers' => '1 Year',
            'gaming' => '1 Year',
            'gaming-consoles' => '1 Year',
            'gaming-accessories' => '6 Months',
            'wearables' => '1 Year',
            'smartwatches' => '1 Year',
            'fitness-trackers' => '1 Year',
            'home-appliances' => '2 Years',
            'kitchen-appliances' => '2 Years',
            'cleaning-appliances' => '2 Years',
            'smart-home' => '1 Year',
            'smart-speakers' => '1 Year',
            'security-cameras' => '1 Year',
            'office-supplies' => '1 Year',
            'office-furniture' => '3 Years',
            'printers-scanners' => '1 Year',
        ];

        // Category-based material mapping
        $categoryMaterialMapping = [
            'accessories' => 'Plastic',
            'computer-accessories' => 'Plastic',
            'mobile-accessories' => 'Plastic',
            'headphones' => 'Plastic',
            'speakers' => 'Plastic',
            'gaming-accessories' => 'Plastic',
            'office-furniture' => 'Metal',
        ];

        $products = Product::with('category')->get();
        $assignedCount = 0;
        $batchSize = 100;
        $processed = 0;

        $this->command->info("Processing {$products->count()} products...");

        foreach ($products as $product) {
            $categorySlug = $product->category?->slug ?? '';

            // Extract brand from product meta_data or name
            $productBrand = null;
            if (!empty($product->meta_data['brand'])) {
                $productBrand = $product->meta_data['brand'];
            } else {
                // Try to extract from product name
                foreach ($brandMapping as $searchBrand => $mappedBrand) {
                    if (stripos($product->name, $searchBrand) !== false) {
                        $productBrand = $searchBrand;
                        break;
                    }
                }
            }

            // Assign Brand attribute
            if ($productBrand) {
                $brandValue = $brandMapping[$productBrand] ?? null;
                if ($brandValue && in_array($brandValue, $brandValues)) {
                    $product->attributes()->syncWithoutDetaching([
                        $brand->id => ['value' => $brandValue]
                    ]);
                    $assignedCount++;
                }
            }

            // Assign Warranty attribute based on category
            $warrantyValue = $categoryWarrantyMapping[$categorySlug] ?? '1 Year';
            if (in_array($warrantyValue, $warrantyValues)) {
                $product->attributes()->syncWithoutDetaching([
                    $warranty->id => ['value' => $warrantyValue]
                ]);
                $assignedCount++;
            }

            // Assign Material attribute for specific categories
            if (isset($categoryMaterialMapping[$categorySlug])) {
                $materialValue = $categoryMaterialMapping[$categorySlug];
                if (in_array($materialValue, $materialValues)) {
                    $product->attributes()->syncWithoutDetaching([
                        $material->id => ['value' => $materialValue]
                    ]);
                    $assignedCount++;
                }
            }

            $processed++;

            // Progress indicator
            if ($processed % $batchSize === 0) {
                $this->command->info("Processed {$processed} products...");
            }
        }

        $this->command->info("Product attribute assignment completed!");
        $this->command->info("Total assignments: {$assignedCount}");
        $this->command->info("Products processed: {$processed}");
    }
}
