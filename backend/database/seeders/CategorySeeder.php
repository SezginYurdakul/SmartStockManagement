<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Manufacturing Categories
     * Based on Netherlands agricultural technology sector
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        // Clear existing categories
        Category::query()->forceDelete();

        // ========================================
        // MAIN CATEGORIES (Parent categories)
        // ========================================

        $tractors = Category::create([
            'company_id' => $companyId,
            'name' => 'Tractors',
            'slug' => 'tractors',
            'description' => 'Agricultural tractors for farming operations'
        ]);

        $harvestingEquipment = Category::create([
            'company_id' => $companyId,
            'name' => 'Harvesting Equipment',
            'slug' => 'harvesting-equipment',
            'description' => 'Combine harvesters, balers, and harvesting machinery'
        ]);

        $soilPreparation = Category::create([
            'company_id' => $companyId,
            'name' => 'Soil Preparation',
            'slug' => 'soil-preparation',
            'description' => 'Ploughs, cultivators, and tillage equipment'
        ]);

        $seedingPlanting = Category::create([
            'company_id' => $companyId,
            'name' => 'Seeding & Planting',
            'slug' => 'seeding-planting',
            'description' => 'Seed drills, planters, and transplanting equipment'
        ]);

        $irrigationSystems = Category::create([
            'company_id' => $companyId,
            'name' => 'Irrigation Systems',
            'slug' => 'irrigation-systems',
            'description' => 'Irrigation and water management equipment'
        ]);

        $sprayingEquipment = Category::create([
            'company_id' => $companyId,
            'name' => 'Spraying Equipment',
            'slug' => 'spraying-equipment',
            'description' => 'Crop sprayers and fertilizer spreaders'
        ]);

        $livestockEquipment = Category::create([
            'company_id' => $companyId,
            'name' => 'Livestock Equipment',
            'slug' => 'livestock-equipment',
            'description' => 'Dairy, feeding, and animal husbandry equipment'
        ]);

        $greenhouseEquipment = Category::create([
            'company_id' => $companyId,
            'name' => 'Greenhouse Equipment',
            'slug' => 'greenhouse-equipment',
            'description' => 'Climate control and greenhouse systems'
        ]);

        $spareParts = Category::create([
            'company_id' => $companyId,
            'name' => 'Spare Parts',
            'slug' => 'spare-parts',
            'description' => 'Replacement parts and components'
        ]);

        $rawMaterials = Category::create([
            'company_id' => $companyId,
            'name' => 'Raw Materials',
            'slug' => 'raw-materials',
            'description' => 'Steel, metals, and manufacturing materials'
        ]);

        // ========================================
        // SUBCATEGORIES - Tractors
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Compact Tractors',
            'slug' => 'compact-tractors',
            'description' => 'Small tractors 25-50 HP for orchards and gardens',
            'parent_id' => $tractors->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Utility Tractors',
            'slug' => 'utility-tractors',
            'description' => 'Medium tractors 50-100 HP for general farming',
            'parent_id' => $tractors->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Row Crop Tractors',
            'slug' => 'row-crop-tractors',
            'description' => 'Large tractors 100-200 HP for field work',
            'parent_id' => $tractors->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Specialty Tractors',
            'slug' => 'specialty-tractors',
            'description' => 'Vineyard, orchard, and narrow tractors',
            'parent_id' => $tractors->id
        ]);

        // ========================================
        // SUBCATEGORIES - Harvesting Equipment
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Combine Harvesters',
            'slug' => 'combine-harvesters',
            'description' => 'Grain and crop combine harvesters',
            'parent_id' => $harvestingEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Forage Harvesters',
            'slug' => 'forage-harvesters',
            'description' => 'Silage and forage harvesting equipment',
            'parent_id' => $harvestingEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Balers',
            'slug' => 'balers',
            'description' => 'Round and square balers for hay and straw',
            'parent_id' => $harvestingEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Potato Harvesters',
            'slug' => 'potato-harvesters',
            'description' => 'Specialized potato and root vegetable harvesters',
            'parent_id' => $harvestingEquipment->id
        ]);

        // ========================================
        // SUBCATEGORIES - Soil Preparation
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Ploughs',
            'slug' => 'ploughs',
            'description' => 'Reversible and conventional ploughs',
            'parent_id' => $soilPreparation->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Disc Harrows',
            'slug' => 'disc-harrows',
            'description' => 'Disc harrows for soil cultivation',
            'parent_id' => $soilPreparation->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Cultivators',
            'slug' => 'cultivators',
            'description' => 'Field cultivators and chisel ploughs',
            'parent_id' => $soilPreparation->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Rotary Tillers',
            'slug' => 'rotary-tillers',
            'description' => 'Rotavators and power tillers',
            'parent_id' => $soilPreparation->id
        ]);

        // ========================================
        // SUBCATEGORIES - Seeding & Planting
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Seed Drills',
            'slug' => 'seed-drills',
            'description' => 'Precision seed drills for row crops',
            'parent_id' => $seedingPlanting->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Planters',
            'slug' => 'planters',
            'description' => 'Pneumatic and mechanical planters',
            'parent_id' => $seedingPlanting->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Transplanters',
            'slug' => 'transplanters',
            'description' => 'Vegetable and seedling transplanters',
            'parent_id' => $seedingPlanting->id
        ]);

        // ========================================
        // SUBCATEGORIES - Irrigation Systems
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Drip Irrigation',
            'slug' => 'drip-irrigation',
            'description' => 'Drip lines and micro-irrigation systems',
            'parent_id' => $irrigationSystems->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Sprinkler Systems',
            'slug' => 'sprinkler-systems',
            'description' => 'Center pivot and linear move sprinklers',
            'parent_id' => $irrigationSystems->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Pumps',
            'slug' => 'pumps',
            'description' => 'Irrigation and water transfer pumps',
            'parent_id' => $irrigationSystems->id
        ]);

        // ========================================
        // SUBCATEGORIES - Spraying Equipment
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Field Sprayers',
            'slug' => 'field-sprayers',
            'description' => 'Mounted and trailed crop sprayers',
            'parent_id' => $sprayingEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Fertilizer Spreaders',
            'slug' => 'fertilizer-spreaders',
            'description' => 'Broadcast and precision fertilizer spreaders',
            'parent_id' => $sprayingEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Orchard Sprayers',
            'slug' => 'orchard-sprayers',
            'description' => 'Air blast sprayers for orchards and vineyards',
            'parent_id' => $sprayingEquipment->id
        ]);

        // ========================================
        // SUBCATEGORIES - Livestock Equipment
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Milking Systems',
            'slug' => 'milking-systems',
            'description' => 'Automated milking parlors and robots',
            'parent_id' => $livestockEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Feeding Systems',
            'slug' => 'feeding-systems',
            'description' => 'Feed mixers and automated feeding equipment',
            'parent_id' => $livestockEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Manure Handling',
            'slug' => 'manure-handling',
            'description' => 'Slurry tankers and manure spreaders',
            'parent_id' => $livestockEquipment->id
        ]);

        // ========================================
        // SUBCATEGORIES - Greenhouse Equipment
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Climate Control',
            'slug' => 'climate-control',
            'description' => 'Heating, ventilation, and cooling systems',
            'parent_id' => $greenhouseEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Greenhouse Irrigation',
            'slug' => 'greenhouse-irrigation',
            'description' => 'Hydroponic and fertigation systems',
            'parent_id' => $greenhouseEquipment->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Automation Systems',
            'slug' => 'automation-systems',
            'description' => 'Greenhouse automation and robotics',
            'parent_id' => $greenhouseEquipment->id
        ]);

        // ========================================
        // SUBCATEGORIES - Spare Parts
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Engine Parts',
            'slug' => 'engine-parts',
            'description' => 'Diesel engine components and filters',
            'parent_id' => $spareParts->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Hydraulic Components',
            'slug' => 'hydraulic-components',
            'description' => 'Hydraulic pumps, cylinders, and valves',
            'parent_id' => $spareParts->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Transmission Parts',
            'slug' => 'transmission-parts',
            'description' => 'Gearbox, clutch, and drivetrain components',
            'parent_id' => $spareParts->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Electrical Components',
            'slug' => 'electrical-components',
            'description' => 'Sensors, wiring, and control units',
            'parent_id' => $spareParts->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Wear Parts',
            'slug' => 'wear-parts',
            'description' => 'Blades, tines, and replacement wear items',
            'parent_id' => $spareParts->id
        ]);

        // ========================================
        // SUBCATEGORIES - Raw Materials
        // ========================================

        Category::create([
            'company_id' => $companyId,
            'name' => 'Steel & Metals',
            'slug' => 'steel-metals',
            'description' => 'Steel sheets, tubes, and metal profiles',
            'parent_id' => $rawMaterials->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Fasteners',
            'slug' => 'fasteners',
            'description' => 'Bolts, nuts, screws, and hardware',
            'parent_id' => $rawMaterials->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Bearings & Seals',
            'slug' => 'bearings-seals',
            'description' => 'Ball bearings, roller bearings, and seals',
            'parent_id' => $rawMaterials->id
        ]);

        Category::create([
            'company_id' => $companyId,
            'name' => 'Rubber & Plastics',
            'slug' => 'rubber-plastics',
            'description' => 'Hoses, belts, and plastic components',
            'parent_id' => $rawMaterials->id
        ]);

        $totalCategories = Category::count();
        $parentCategories = Category::whereNull('parent_id')->count();
        $childCategories = Category::whereNotNull('parent_id')->count();

        $this->command->info("Agricultural Machinery categories seeded successfully!");
        $this->command->info("Total: {$totalCategories} categories ({$parentCategories} parent + {$childCategories} subcategories)");
    }
}
