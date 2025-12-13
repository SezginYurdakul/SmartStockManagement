<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Assigns attributes to categories based on category type
     */
    public function run(): void
    {
        // Get all attributes
        $color = Attribute::where('name', 'color')->first();
        $size = Attribute::where('name', 'size')->first();
        $storage = Attribute::where('name', 'storage')->first();
        $ram = Attribute::where('name', 'ram')->first();
        $material = Attribute::where('name', 'material')->first();
        $brand = Attribute::where('name', 'brand')->first();
        $warranty = Attribute::where('name', 'warranty')->first();
        $screenSize = Attribute::where('name', 'screen_size')->first();

        if (!$color || !$brand) {
            $this->command->warn('Attributes not found! Please run AttributeSeeder first.');
            return;
        }

        // Category attribute mappings
        // Format: 'category-slug' => [attribute_id => ['is_required' => bool, 'order' => int]]
        $categoryAttributeMappings = [
            // Electronics - general
            'electronics' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $warranty->id => ['is_required' => false, 'order' => 2],
                $color->id => ['is_required' => false, 'order' => 3],
            ],
            'tablets' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $storage->id => ['is_required' => true, 'order' => 2],
                $screenSize->id => ['is_required' => true, 'order' => 3],
                $color->id => ['is_required' => false, 'order' => 4],
                $warranty->id => ['is_required' => false, 'order' => 5],
            ],
            'accessories' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $material->id => ['is_required' => false, 'order' => 3],
            ],

            // Computers & Laptops
            'computers-laptops' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $ram->id => ['is_required' => true, 'order' => 2],
                $storage->id => ['is_required' => true, 'order' => 3],
                $screenSize->id => ['is_required' => false, 'order' => 4],
                $warranty->id => ['is_required' => false, 'order' => 5],
            ],
            'laptops' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $ram->id => ['is_required' => true, 'order' => 2],
                $storage->id => ['is_required' => true, 'order' => 3],
                $screenSize->id => ['is_required' => true, 'order' => 4],
                $color->id => ['is_required' => false, 'order' => 5],
                $warranty->id => ['is_required' => false, 'order' => 6],
            ],
            'desktops' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $ram->id => ['is_required' => true, 'order' => 2],
                $storage->id => ['is_required' => true, 'order' => 3],
                $warranty->id => ['is_required' => false, 'order' => 4],
            ],
            'computer-accessories' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $material->id => ['is_required' => false, 'order' => 3],
            ],

            // Mobile Phones
            'mobile-phones' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $storage->id => ['is_required' => true, 'order' => 2],
                $ram->id => ['is_required' => true, 'order' => 3],
                $color->id => ['is_required' => true, 'order' => 4],
                $screenSize->id => ['is_required' => false, 'order' => 5],
                $warranty->id => ['is_required' => false, 'order' => 6],
            ],
            'smartphones' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $storage->id => ['is_required' => true, 'order' => 2],
                $ram->id => ['is_required' => true, 'order' => 3],
                $color->id => ['is_required' => true, 'order' => 4],
                $screenSize->id => ['is_required' => false, 'order' => 5],
                $warranty->id => ['is_required' => false, 'order' => 6],
            ],
            'mobile-accessories' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $material->id => ['is_required' => false, 'order' => 3],
            ],

            // Cameras & Photography
            'cameras-photography' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $warranty->id => ['is_required' => false, 'order' => 2],
            ],
            'dslr-cameras' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $warranty->id => ['is_required' => false, 'order' => 2],
            ],
            'mirrorless-cameras' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $warranty->id => ['is_required' => false, 'order' => 2],
            ],
            'lenses' => [
                $brand->id => ['is_required' => true, 'order' => 1],
            ],

            // Audio & Headphones
            'audio-headphones' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'headphones' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => true, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'speakers' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],

            // Gaming
            'gaming' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $storage->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'gaming-consoles' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $storage->id => ['is_required' => true, 'order' => 2],
                $color->id => ['is_required' => false, 'order' => 3],
                $warranty->id => ['is_required' => false, 'order' => 4],
            ],
            'gaming-accessories' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
            ],

            // Wearables
            'wearables' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => true, 'order' => 2],
                $size->id => ['is_required' => false, 'order' => 3],
                $warranty->id => ['is_required' => false, 'order' => 4],
            ],
            'smartwatches' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => true, 'order' => 2],
                $size->id => ['is_required' => true, 'order' => 3],
                $storage->id => ['is_required' => false, 'order' => 4],
                $warranty->id => ['is_required' => false, 'order' => 5],
            ],
            'fitness-trackers' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => true, 'order' => 2],
                $size->id => ['is_required' => false, 'order' => 3],
                $warranty->id => ['is_required' => false, 'order' => 4],
            ],

            // Home Appliances
            'home-appliances' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'kitchen-appliances' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'cleaning-appliances' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],

            // Smart Home
            'smart-home' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'smart-speakers' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => true, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'security-cameras' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],

            // Office Supplies
            'office-supplies' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
            'office-furniture' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $material->id => ['is_required' => false, 'order' => 3],
            ],
            'printers-scanners' => [
                $brand->id => ['is_required' => true, 'order' => 1],
                $color->id => ['is_required' => false, 'order' => 2],
                $warranty->id => ['is_required' => false, 'order' => 3],
            ],
        ];

        $assignedCount = 0;

        foreach ($categoryAttributeMappings as $categorySlug => $attributes) {
            $category = Category::where('slug', $categorySlug)->first();

            if (!$category) {
                $this->command->warn("Category not found: {$categorySlug}");
                continue;
            }

            foreach ($attributes as $attributeId => $pivotData) {
                $category->attributes()->syncWithoutDetaching([
                    $attributeId => $pivotData
                ]);
                $assignedCount++;
            }

            $this->command->info("Assigned " . count($attributes) . " attributes to: {$category->name}");
        }

        $this->command->info("Category attribute assignment completed! Total: {$assignedCount} assignments");
    }
}
