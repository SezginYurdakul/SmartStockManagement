<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Bom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Products for Netherlands Market
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        // Only get subcategories (categories with parent_id)
        $categories = Category::whereNotNull('parent_id')->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found! Please run CategorySeeder first.');
            return;
        }

        // Get product types
        $finishedGoodsType = ProductType::where('code', 'FG')->first();
        $sparePartsType = ProductType::where('code', 'SP')->first();
        $rawMaterialsType = ProductType::where('code', 'RM')->first();

        if (!$finishedGoodsType) {
            $this->command->warn('No product types found! Please run ProductTypeSeeder first.');
            return;
        }

        // Agricultural Machinery product templates for each category
        $productTemplates = [
            // ========================================
            // TRACTORS
            // ========================================
            'compact-tractors' => [
                ['name' => 'AT-2540 Compact', 'price' => 28500, 'brand' => 'AgriTech NL', 'power' => '25 HP'],
                ['name' => 'AT-3045 Orchard', 'price' => 32000, 'brand' => 'AgriTech NL', 'power' => '35 HP'],
                ['name' => 'HA-C30 Garden Pro', 'price' => 26500, 'brand' => 'HollandAgro', 'power' => '35 HP'],
                ['name' => 'EF-Mini 40', 'price' => 35000, 'brand' => 'EuroFarm', 'power' => '50 HP'],
                ['name' => '4E Series 320', 'price' => 29500, 'brand' => 'Deutz-Fahr', 'power' => '35 HP'],
            ],
            'utility-tractors' => [
                ['name' => 'AT-5075 Utility', 'price' => 52000, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'AT-6090 Field Master', 'price' => 68000, 'brand' => 'AgriTech NL', 'power' => '100 HP'],
                ['name' => 'HA-U80 Universal', 'price' => 58000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
                ['name' => 'EF-Utility 95', 'price' => 72000, 'brand' => 'EuroFarm', 'power' => '100 HP'],
                ['name' => '5D TTV 110', 'price' => 85000, 'brand' => 'Deutz-Fahr', 'power' => '100 HP'],
            ],
            'row-crop-tractors' => [
                ['name' => 'AT-120 RowMaster', 'price' => 125000, 'brand' => 'AgriTech NL', 'power' => '120 HP'],
                ['name' => 'AT-150 PowerLine', 'price' => 165000, 'brand' => 'AgriTech NL', 'power' => '150 HP'],
                ['name' => 'HA-RC180', 'price' => 195000, 'brand' => 'HollandAgro', 'power' => '180 HP'],
                ['name' => 'EF-180 Pro', 'price' => 185000, 'brand' => 'EuroFarm', 'power' => '180 HP'],
                ['name' => '6 Series 6155', 'price' => 175000, 'brand' => 'Deutz-Fahr', 'power' => '150 HP'],
            ],
            'specialty-tractors' => [
                ['name' => 'AT-V65 Vineyard', 'price' => 48000, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'AT-O55 Orchard Narrow', 'price' => 42000, 'brand' => 'AgriTech NL', 'power' => '50 HP'],
                ['name' => 'HA-Vine 70', 'price' => 52000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
                ['name' => 'EF-Narrow 60', 'price' => 46000, 'brand' => 'EuroFarm', 'power' => '50 HP'],
                ['name' => 'Agroplus F Ecoline', 'price' => 55000, 'brand' => 'Deutz-Fahr', 'power' => '75 HP'],
            ],

            // ========================================
            // HARVESTING EQUIPMENT
            // ========================================
            'combine-harvesters' => [
                ['name' => 'AT-CH850 Grain Master', 'price' => 385000, 'brand' => 'AgriTech NL', 'power' => '300 HP'],
                ['name' => 'AT-CH650 Flex', 'price' => 295000, 'brand' => 'AgriTech NL', 'power' => '250 HP'],
                ['name' => 'HA-Combine 7500', 'price' => 425000, 'brand' => 'HollandAgro', 'power' => '400 HP'],
                ['name' => 'EF-Harvester 620', 'price' => 365000, 'brand' => 'EuroFarm', 'power' => '300 HP'],
                ['name' => 'C7206 TS', 'price' => 345000, 'brand' => 'Deutz-Fahr', 'power' => '250 HP'],
            ],
            'forage-harvesters' => [
                ['name' => 'AT-FH450 Silage Pro', 'price' => 285000, 'brand' => 'AgriTech NL', 'power' => '400 HP'],
                ['name' => 'AT-FH350 Forage', 'price' => 225000, 'brand' => 'AgriTech NL', 'power' => '300 HP'],
                ['name' => 'HA-Forage 550', 'price' => 345000, 'brand' => 'HollandAgro', 'power' => '400 HP'],
                ['name' => 'EF-Silage 400', 'price' => 265000, 'brand' => 'EuroFarm', 'power' => '300 HP'],
                ['name' => 'Jaguar 850', 'price' => 395000, 'brand' => 'Kverneland', 'power' => '400 HP'],
            ],
            'balers' => [
                ['name' => 'AT-RB150 Round Baler', 'price' => 42000, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'AT-SB250 Square', 'price' => 68000, 'brand' => 'AgriTech NL', 'power' => '100 HP'],
                ['name' => 'HA-Baler Pro', 'price' => 55000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
                ['name' => 'EF-RoundPack 180', 'price' => 48000, 'brand' => 'EuroFarm', 'power' => '75 HP'],
                ['name' => 'Fortima V 1800', 'price' => 52000, 'brand' => 'Kverneland', 'power' => '75 HP'],
            ],
            'potato-harvesters' => [
                ['name' => 'GT170 2-Row', 'price' => 185000, 'brand' => 'Grimme', 'power' => '150 HP'],
                ['name' => 'SE 150-60 4-Row', 'price' => 325000, 'brand' => 'Grimme', 'power' => '200 HP'],
                ['name' => 'AT-PH200 Potato Master', 'price' => 195000, 'brand' => 'AgriTech NL', 'power' => '150 HP'],
                ['name' => 'HA-Tuber 300', 'price' => 245000, 'brand' => 'HollandAgro', 'power' => '180 HP'],
                ['name' => 'Varitron 470', 'price' => 385000, 'brand' => 'Grimme', 'power' => '250 HP'],
            ],

            // ========================================
            // SOIL PREPARATION
            // ========================================
            'ploughs' => [
                ['name' => 'Vari-Titan 10', 'price' => 28500, 'brand' => 'Lemken', 'power' => '150 HP'],
                ['name' => 'EurOpal 8', 'price' => 22500, 'brand' => 'Lemken', 'power' => '120 HP'],
                ['name' => 'AT-P5 Reversible', 'price' => 18500, 'brand' => 'AgriTech NL', 'power' => '100 HP'],
                ['name' => 'HA-Plough Pro 7', 'price' => 24500, 'brand' => 'HollandAgro', 'power' => '120 HP'],
                ['name' => '2500 Series 6-Furrow', 'price' => 32000, 'brand' => 'Kverneland', 'power' => '180 HP'],
            ],
            'disc-harrows' => [
                ['name' => 'Rubin 12', 'price' => 48500, 'brand' => 'Lemken', 'power' => '180 HP'],
                ['name' => 'Heliodor 9', 'price' => 35500, 'brand' => 'Lemken', 'power' => '120 HP'],
                ['name' => 'AT-DH400 Disc Pro', 'price' => 32000, 'brand' => 'AgriTech NL', 'power' => '150 HP'],
                ['name' => 'Catros 6001-2', 'price' => 42000, 'brand' => 'Amazone', 'power' => '180 HP'],
                ['name' => 'HA-Disc 500', 'price' => 38000, 'brand' => 'HollandAgro', 'power' => '150 HP'],
            ],
            'cultivators' => [
                ['name' => 'Karat 12', 'price' => 65000, 'brand' => 'Lemken', 'power' => '200 HP'],
                ['name' => 'Cenius 4003-2', 'price' => 52000, 'brand' => 'Amazone', 'power' => '180 HP'],
                ['name' => 'AT-C600 Chisel', 'price' => 28500, 'brand' => 'AgriTech NL', 'power' => '120 HP'],
                ['name' => 'CLC Pro 450', 'price' => 48000, 'brand' => 'Kverneland', 'power' => '180 HP'],
                ['name' => 'HA-Culti 500', 'price' => 35000, 'brand' => 'HollandAgro', 'power' => '150 HP'],
            ],
            'rotary-tillers' => [
                ['name' => 'Zirkon 12', 'price' => 42000, 'brand' => 'Lemken', 'power' => '150 HP'],
                ['name' => 'AT-RT300 Power Tiller', 'price' => 18500, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'KE 4000 Special', 'price' => 48000, 'brand' => 'Amazone', 'power' => '180 HP'],
                ['name' => 'HA-Rotary 400', 'price' => 32000, 'brand' => 'HollandAgro', 'power' => '120 HP'],
                ['name' => 'EF-Tiller Pro', 'price' => 24500, 'brand' => 'EuroFarm', 'power' => '100 HP'],
            ],

            // ========================================
            // SEEDING & PLANTING
            // ========================================
            'seed-drills' => [
                ['name' => 'ED 6000-2', 'price' => 85000, 'brand' => 'Amazone', 'power' => '150 HP'],
                ['name' => 'Solitair 12', 'price' => 95000, 'brand' => 'Lemken', 'power' => '180 HP'],
                ['name' => 'AT-SD600 Precision', 'price' => 68000, 'brand' => 'AgriTech NL', 'power' => '120 HP'],
                ['name' => 'u-drill 6000', 'price' => 78000, 'brand' => 'Kverneland', 'power' => '150 HP'],
                ['name' => 'HA-Seed Pro 800', 'price' => 72000, 'brand' => 'HollandAgro', 'power' => '150 HP'],
            ],
            'planters' => [
                ['name' => 'GL 660 Potato Planter', 'price' => 125000, 'brand' => 'Grimme', 'power' => '120 HP'],
                ['name' => 'Precea 6000-2', 'price' => 145000, 'brand' => 'Amazone', 'power' => '150 HP'],
                ['name' => 'AT-PL8 Precision', 'price' => 95000, 'brand' => 'AgriTech NL', 'power' => '120 HP'],
                ['name' => 'Azurit 12', 'price' => 135000, 'brand' => 'Lemken', 'power' => '150 HP'],
                ['name' => 'Optima TF Profi', 'price' => 115000, 'brand' => 'Kverneland', 'power' => '120 HP'],
            ],
            'transplanters' => [
                ['name' => 'AT-TP4 Transplanter', 'price' => 28500, 'brand' => 'AgriTech NL', 'power' => '50 HP'],
                ['name' => 'HA-Plant 6-Row', 'price' => 42000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
                ['name' => 'EF-Trans Pro', 'price' => 35000, 'brand' => 'EuroFarm', 'power' => '50 HP'],
                ['name' => 'Vegetable Setter 8', 'price' => 48000, 'brand' => 'Grimme', 'power' => '75 HP'],
                ['name' => 'AT-TP6 Semi-Auto', 'price' => 38500, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
            ],

            // ========================================
            // IRRIGATION SYSTEMS
            // ========================================
            'drip-irrigation' => [
                ['name' => 'DripLine Pro 5000', 'price' => 12500, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'AT-Drip Master', 'price' => 8500, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'HA-MicroDrip 3000', 'price' => 9800, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Precision Drip System', 'price' => 15500, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'EF-Drip Economy', 'price' => 6500, 'brand' => 'EuroFarm', 'power' => '0 HP'],
            ],
            'sprinkler-systems' => [
                ['name' => 'Center Pivot 800', 'price' => 125000, 'brand' => 'AgriTech NL', 'power' => '25 HP'],
                ['name' => 'Linear Move Pro', 'price' => 145000, 'brand' => 'HollandAgro', 'power' => '35 HP'],
                ['name' => 'AT-Pivot 1200', 'price' => 165000, 'brand' => 'AgriTech NL', 'power' => '50 HP'],
                ['name' => 'HA-Sprinkler 600', 'price' => 85000, 'brand' => 'HollandAgro', 'power' => '25 HP'],
                ['name' => 'EF-Irrigation Pro', 'price' => 95000, 'brand' => 'EuroFarm', 'power' => '35 HP'],
            ],
            'pumps' => [
                ['name' => 'AT-P100 Irrigation Pump', 'price' => 4500, 'brand' => 'AgriTech NL', 'power' => '25 HP'],
                ['name' => 'HA-Pump 200', 'price' => 6800, 'brand' => 'HollandAgro', 'power' => '35 HP'],
                ['name' => 'Submersible S500', 'price' => 3200, 'brand' => 'Priva', 'power' => '25 HP'],
                ['name' => 'EF-Transfer 150', 'price' => 2800, 'brand' => 'EuroFarm', 'power' => '25 HP'],
                ['name' => 'High-Flow 300', 'price' => 8500, 'brand' => 'AgriTech NL', 'power' => '50 HP'],
            ],

            // ========================================
            // SPRAYING EQUIPMENT
            // ========================================
            'field-sprayers' => [
                ['name' => 'UX 5201 Super', 'price' => 185000, 'brand' => 'Amazone', 'power' => '180 HP'],
                ['name' => 'Pantera 4502', 'price' => 265000, 'brand' => 'Amazone', 'power' => '200 HP'],
                ['name' => 'AT-SP3000 Trailed', 'price' => 85000, 'brand' => 'AgriTech NL', 'power' => '100 HP'],
                ['name' => 'iXter B 1200', 'price' => 42000, 'brand' => 'Kverneland', 'power' => '75 HP'],
                ['name' => 'HA-Spray Master', 'price' => 95000, 'brand' => 'HollandAgro', 'power' => '120 HP'],
            ],
            'fertilizer-spreaders' => [
                ['name' => 'ZA-TS 4200', 'price' => 28500, 'brand' => 'Amazone', 'power' => '100 HP'],
                ['name' => 'ZG-TS 10001', 'price' => 85000, 'brand' => 'Amazone', 'power' => '150 HP'],
                ['name' => 'AT-FS2000 Broadcast', 'price' => 18500, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'Exacta CL 1500', 'price' => 15500, 'brand' => 'Kverneland', 'power' => '50 HP'],
                ['name' => 'HA-Spread Pro', 'price' => 22000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
            ],
            'orchard-sprayers' => [
                ['name' => 'AT-OS500 Air Blast', 'price' => 32000, 'brand' => 'AgriTech NL', 'power' => '50 HP'],
                ['name' => 'HA-Orchard 800', 'price' => 42000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
                ['name' => 'EF-Vineyard Pro', 'price' => 28500, 'brand' => 'EuroFarm', 'power' => '50 HP'],
                ['name' => 'Tunnel Sprayer T1000', 'price' => 55000, 'brand' => 'AgriTech NL', 'power' => '75 HP'],
                ['name' => 'Low-Drift 600', 'price' => 38000, 'brand' => 'Kverneland', 'power' => '50 HP'],
            ],

            // ========================================
            // LIVESTOCK EQUIPMENT
            // ========================================
            'milking-systems' => [
                ['name' => 'Astronaut A5', 'price' => 185000, 'brand' => 'Lely', 'power' => '0 HP'],
                ['name' => 'Vector Feeding', 'price' => 125000, 'brand' => 'Lely', 'power' => '0 HP'],
                ['name' => 'AT-Milk Pro 8', 'price' => 65000, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'HA-DairyLine 12', 'price' => 85000, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Discovery 120', 'price' => 95000, 'brand' => 'Lely', 'power' => '0 HP'],
            ],
            'feeding-systems' => [
                ['name' => 'Juno 150', 'price' => 42000, 'brand' => 'Lely', 'power' => '0 HP'],
                ['name' => 'AT-Feed Mix 10', 'price' => 48000, 'brand' => 'AgriTech NL', 'power' => '100 HP'],
                ['name' => 'HA-Mixer 15', 'price' => 55000, 'brand' => 'HollandAgro', 'power' => '100 HP'],
                ['name' => 'TMR Mixer 18', 'price' => 62000, 'brand' => 'EuroFarm', 'power' => '120 HP'],
                ['name' => 'Vector 300', 'price' => 165000, 'brand' => 'Lely', 'power' => '0 HP'],
            ],
            'manure-handling' => [
                ['name' => 'AT-Slurry 8000', 'price' => 45000, 'brand' => 'AgriTech NL', 'power' => '150 HP'],
                ['name' => 'HA-Tanker 12000', 'price' => 65000, 'brand' => 'HollandAgro', 'power' => '180 HP'],
                ['name' => 'Manure Spreader Pro', 'price' => 38000, 'brand' => 'EuroFarm', 'power' => '120 HP'],
                ['name' => 'Injector 6000', 'price' => 52000, 'brand' => 'AgriTech NL', 'power' => '150 HP'],
                ['name' => 'Slurry Pump Station', 'price' => 28000, 'brand' => 'HollandAgro', 'power' => '75 HP'],
            ],

            // ========================================
            // GREENHOUSE EQUIPMENT
            // ========================================
            'climate-control' => [
                ['name' => 'Connext Climate', 'price' => 85000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'AT-Climate Pro', 'price' => 45000, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'HA-Vent System', 'price' => 32000, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Heating Control Plus', 'price' => 55000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'Screen System 4000', 'price' => 42000, 'brand' => 'Priva', 'power' => '0 HP'],
            ],
            'greenhouse-irrigation' => [
                ['name' => 'Nutrifit 100', 'price' => 65000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'AT-Hydro System', 'price' => 42000, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Fertigation Pro', 'price' => 55000, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'NFT Growing System', 'price' => 38000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'EF-Greenhouse Drip', 'price' => 28000, 'brand' => 'EuroFarm', 'power' => '0 HP'],
            ],
            'automation-systems' => [
                ['name' => 'Compact 3 Operator', 'price' => 125000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'AT-Greenhouse Robot', 'price' => 185000, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Harvest Automation', 'price' => 145000, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Sorting Line Pro', 'price' => 95000, 'brand' => 'Priva', 'power' => '0 HP'],
                ['name' => 'Pack Station Auto', 'price' => 75000, 'brand' => 'EuroFarm', 'power' => '0 HP'],
            ],

            // ========================================
            // SPARE PARTS
            // ========================================
            'engine-parts' => [
                ['name' => 'Fuel Filter Kit', 'price' => 85, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Oil Filter Set', 'price' => 45, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Air Filter Element', 'price' => 125, 'brand' => 'Deutz-Fahr', 'power' => '0 HP'],
                ['name' => 'Injector Nozzle', 'price' => 285, 'brand' => 'EuroFarm', 'power' => '0 HP'],
                ['name' => 'Fan Belt', 'price' => 65, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
            ],
            'hydraulic-components' => [
                ['name' => 'Hydraulic Pump', 'price' => 1850, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Cylinder Kit', 'price' => 950, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Control Valve', 'price' => 680, 'brand' => 'EuroFarm', 'power' => '0 HP'],
                ['name' => 'Hose Assembly Set', 'price' => 245, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Hydraulic Filter', 'price' => 125, 'brand' => 'Deutz-Fahr', 'power' => '0 HP'],
            ],
            'transmission-parts' => [
                ['name' => 'Clutch Plate', 'price' => 485, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'PTO Shaft', 'price' => 650, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'Gear Set', 'price' => 1250, 'brand' => 'Deutz-Fahr', 'power' => '0 HP'],
                ['name' => 'Bearing Kit', 'price' => 185, 'brand' => 'EuroFarm', 'power' => '0 HP'],
                ['name' => 'U-Joint Cross', 'price' => 95, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
            ],
            'electrical-components' => [
                ['name' => 'Alternator 120A', 'price' => 385, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Starter Motor', 'price' => 450, 'brand' => 'HollandAgro', 'power' => '0 HP'],
                ['name' => 'ECU Module', 'price' => 1850, 'brand' => 'Deutz-Fahr', 'power' => '0 HP'],
                ['name' => 'Wiring Harness', 'price' => 285, 'brand' => 'EuroFarm', 'power' => '0 HP'],
                ['name' => 'Sensor Kit', 'price' => 165, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
            ],
            'wear-parts' => [
                ['name' => 'Plough Share Set', 'price' => 185, 'brand' => 'Lemken', 'power' => '0 HP'],
                ['name' => 'Disc Blade Pack', 'price' => 320, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Cultivator Tine', 'price' => 45, 'brand' => 'Amazone', 'power' => '0 HP'],
                ['name' => 'Knife Section', 'price' => 8, 'brand' => 'Kverneland', 'power' => '0 HP'],
                ['name' => 'Seed Coulter', 'price' => 125, 'brand' => 'HollandAgro', 'power' => '0 HP'],
            ],

            // ========================================
            // RAW MATERIALS
            // ========================================
            'steel-metals' => [
                ['name' => 'Steel Sheet 3mm', 'price' => 85, 'brand' => 'Dutch Steel', 'power' => '0 HP'],
                ['name' => 'Steel Tube 50x50', 'price' => 42, 'brand' => 'EuroMetal', 'power' => '0 HP'],
                ['name' => 'Hardox 450 Plate', 'price' => 285, 'brand' => 'SSAB', 'power' => '0 HP'],
                ['name' => 'Aluminum Profile', 'price' => 65, 'brand' => 'AlcoNL', 'power' => '0 HP'],
                ['name' => 'Cast Iron Block', 'price' => 125, 'brand' => 'Dutch Steel', 'power' => '0 HP'],
            ],
            'fasteners' => [
                ['name' => 'Bolt Set M12', 'price' => 25, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Nut Pack M10', 'price' => 18, 'brand' => 'EuroFast', 'power' => '0 HP'],
                ['name' => 'Self-Tapping Screws', 'price' => 12, 'brand' => 'FastenPro', 'power' => '0 HP'],
                ['name' => 'Lock Washer Set', 'price' => 8, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Split Pin Pack', 'price' => 6, 'brand' => 'EuroFast', 'power' => '0 HP'],
            ],
            'bearings-seals' => [
                ['name' => 'Ball Bearing 6205', 'price' => 28, 'brand' => 'SKF', 'power' => '0 HP'],
                ['name' => 'Roller Bearing 32206', 'price' => 65, 'brand' => 'FAG', 'power' => '0 HP'],
                ['name' => 'Oil Seal Set', 'price' => 35, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'O-Ring Kit', 'price' => 22, 'brand' => 'Simrit', 'power' => '0 HP'],
                ['name' => 'Dust Cover Pack', 'price' => 15, 'brand' => 'SKF', 'power' => '0 HP'],
            ],
            'rubber-plastics' => [
                ['name' => 'Hydraulic Hose 12mm', 'price' => 45, 'brand' => 'Gates', 'power' => '0 HP'],
                ['name' => 'V-Belt A68', 'price' => 28, 'brand' => 'Optibelt', 'power' => '0 HP'],
                ['name' => 'Fuel Hose 8mm', 'price' => 18, 'brand' => 'AgriTech NL', 'power' => '0 HP'],
                ['name' => 'Plastic Tank 200L', 'price' => 145, 'brand' => 'EuroPlas', 'power' => '0 HP'],
                ['name' => 'Rubber Mount', 'price' => 25, 'brand' => 'Vibracoustic', 'power' => '0 HP'],
            ],
        ];

        $conditions = ['New', 'Demo Unit', 'Factory Fresh'];
        $variants = ['Standard', 'Pro', 'Plus', 'Premium', 'Elite', 'Base', 'Advanced'];

        // Map category slugs to product types
        $sparePartsCategories = ['engine-parts', 'hydraulic-components', 'transmission-parts', 'electrical-components', 'wear-parts'];
        $rawMaterialsCategories = ['steel-metals', 'fasteners', 'bearings-seals', 'rubber-plastics'];

        Product::withoutSyncingToSearch(function () use ($categories, $productTemplates, $conditions, $variants, $companyId, $finishedGoodsType, $sparePartsType, $rawMaterialsType, $sparePartsCategories, $rawMaterialsCategories) {
            foreach ($categories as $category) {
                $templates = $productTemplates[$category->slug] ?? [];

                if (empty($templates)) {
                    continue;
                }

                $this->command->info("Creating products for category: {$category->name}");

                $productsCreated = 0;
                $templateIndex = 0;

                // Create products per subcategory (fewer for spare parts/raw materials)
                $maxProducts = in_array($category->slug, array_merge($sparePartsCategories, $rawMaterialsCategories)) ? 20 : 25;

                while ($productsCreated < $maxProducts) {
                    $template = $templates[$templateIndex % count($templates)];

                    // Add variation to make products unique
                    $variant = $variants[$productsCreated % count($variants)];
                    $condition = $conditions[$productsCreated % count($conditions)];

                    $productName = "{$template['brand']} {$template['name']} - {$variant}";
                    $slug = Str::slug($productName);
                    // Use category ID to ensure uniqueness across categories with similar prefixes
                    $sku = strtoupper(substr(Str::slug($template['brand']), 0, 3)) . '-C' . $category->id . '-' . str_pad($productsCreated + 1, 4, '0', STR_PAD_LEFT);

                    // Price variation
                    $basePrice = $template['price'];
                    $priceVariation = ($productsCreated % 5) * ($basePrice * 0.05); // 0-20% variation
                    $price = $basePrice + $priceVariation;
                    $comparePrice = $price + ($price * 0.15); // 15% higher compare price
                    $costPrice = $price * 0.65; // 65% of selling price (machinery has lower margins)

                    // Stock variation
                    $stock = in_array($category->slug, array_merge($sparePartsCategories, $rawMaterialsCategories))
                        ? rand(50, 500) // More stock for parts/materials
                        : rand(2, 15);  // Less stock for machinery

                    $lowStockThreshold = in_array($category->slug, array_merge($sparePartsCategories, $rawMaterialsCategories))
                        ? rand(20, 50)
                        : rand(1, 3);

                    // Random active/featured status
                    $isActive = $productsCreated < 15 ? true : (rand(0, 1) === 1);
                    $isFeatured = $productsCreated < 3 ? true : (rand(0, 10) > 8);

                    // Determine product type based on category
                    $productTypeId = $finishedGoodsType->id;
                    if (in_array($category->slug, $sparePartsCategories)) {
                        $productTypeId = $sparePartsType?->id ?? $finishedGoodsType->id;
                    } elseif (in_array($category->slug, $rawMaterialsCategories)) {
                        $productTypeId = $rawMaterialsType?->id ?? $finishedGoodsType->id;
                    }

                    // MRP Planning fields based on product type
                    $isFinishedGoods = $productTypeId === $finishedGoodsType->id;
                    $isSpareParts = in_array($category->slug, $sparePartsCategories);
                    $isRawMaterials = in_array($category->slug, $rawMaterialsCategories);

                    // Lead time: machinery takes longer, parts/materials are quicker
                    $leadTimeDays = $isFinishedGoods ? rand(14, 45) : ($isSpareParts ? rand(3, 10) : rand(1, 5));

                    // Safety stock: higher for fast-moving items
                    $safetyStock = $isRawMaterials ? rand(20, 100) : ($isSpareParts ? rand(5, 25) : rand(1, 3));

                    // Reorder point = safety stock + average daily usage * lead time
                    $reorderPoint = $safetyStock + ($stock * 0.1 * $leadTimeDays / 30);

                    // Make or buy decision
                    $makeOrBuy = $isFinishedGoods ? 'make' : 'buy';

                    // Minimum order qty and multiple
                    $minimumOrderQty = $isRawMaterials ? rand(10, 50) : ($isSpareParts ? rand(2, 10) : 1);
                    $orderMultiple = $isRawMaterials ? rand(5, 25) : ($isSpareParts ? rand(1, 5) : 1);

                    $product = Product::create([
                        'company_id' => $companyId,
                        'product_type_id' => $productTypeId,
                        'name' => $productName,
                        'slug' => $slug,
                        'sku' => $sku,
                        'description' => "Professional-grade {$template['name']} from {$template['brand']}. Condition: {$condition}. Designed for Dutch and European agricultural operations. " . ($template['power'] !== '0 HP' ? "Power requirement: {$template['power']}." : ''),
                        'short_description' => "{$template['brand']} {$template['name']} - {$variant}",
                        'price' => number_format($price, 2, '.', ''),
                        'compare_price' => number_format($comparePrice, 2, '.', ''),
                        'cost_price' => number_format($costPrice, 2, '.', ''),
                        'stock' => $stock,
                        'low_stock_threshold' => $lowStockThreshold,
                        'is_active' => $isActive,
                        'is_featured' => $isFeatured,
                        // MRP Planning fields
                        'lead_time_days' => $leadTimeDays,
                        'safety_stock' => $safetyStock,
                        'reorder_point' => round($reorderPoint, 2),
                        'make_or_buy' => $makeOrBuy,
                        'minimum_order_qty' => $minimumOrderQty,
                        'order_multiple' => $orderMultiple,
                        'meta_data' => [
                            'brand' => $template['brand'],
                            'power_requirement' => $template['power'],
                            'condition' => $condition,
                            'variant' => $variant,
                            'origin' => 'Netherlands/EU',
                        ],
                    ]);

                    // Attach category as primary
                    $product->categories()->attach($category->id, ['is_primary' => true]);

                    $productsCreated++;
                    $templateIndex++;
                }

                $this->command->info("✓ Created {$productsCreated} products for {$category->name}");
            }
        });

        $totalProducts = Product::count();
        $this->command->info("Agricultural Machinery products seeded successfully! Total: {$totalProducts} products");

        // Calculate Low-Level Codes for all products
        $this->command->info("Calculating Low-Level Codes for products...");
        $this->calculateLowLevelCodes($companyId);
        $this->command->info("✓ Low-Level Codes calculated successfully!");
    }

    /**
     * Calculate Low-Level Codes for all products
     * Low-Level Code determines the processing order in MRP:
     * - Level 0: Finished goods (not used as components)
     * - Level 1+: Components used in higher-level products
     */
    protected function calculateLowLevelCodes(?int $companyId): void
    {
        if (!$companyId) {
            return;
        }

        // Reset all low-level codes to 0
        Product::where('company_id', $companyId)
            ->update(['low_level_code' => 0]);

        $changed = true;
        $maxIterations = 100; // Prevent infinite loops
        $iteration = 0;

        while ($changed && $iteration < $maxIterations) {
            $changed = false;
            $iteration++;

            // Get all active BOMs
            $boms = Bom::where('company_id', $companyId)
                ->where('status', 'active')
                ->with('items.component')
                ->get();

            foreach ($boms as $bom) {
                $parentProduct = $bom->product;
                if (!$parentProduct || !$parentProduct->is_active) {
                    continue;
                }

                $parentLevel = $parentProduct->low_level_code ?? 0;

                // Check all components in this BOM
                foreach ($bom->items as $item) {
                    $component = $item->component;
                    if (!$component || !$component->is_active) {
                        continue;
                    }

                    // Component's level should be at least parent's level + 1
                    $requiredLevel = $parentLevel + 1;
                    $currentLevel = $component->low_level_code ?? 0;

                    if ($requiredLevel > $currentLevel) {
                        $component->low_level_code = $requiredLevel;
                        $component->save();
                        $changed = true;
                    }
                }
            }
        }

        if ($iteration >= $maxIterations) {
            $this->command->warn('Low-Level Code calculation reached maximum iterations. Possible circular BOM reference.');
        }

        // Show statistics
        $levelStats = Product::where('company_id', $companyId)
            ->selectRaw('low_level_code, COUNT(*) as count')
            ->groupBy('low_level_code')
            ->orderBy('low_level_code')
            ->pluck('count', 'low_level_code')
            ->toArray();

        foreach ($levelStats as $level => $count) {
            $this->command->info("  Level {$level}: {$count} products");
        }
    }
}
