<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductUomConversion;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class ProductUomConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates product-specific UOM conversions for demo purposes.
     * These override standard conversions for specific products.
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        if (!$companyId) {
            $this->command->warn('No company found, skipping ProductUomConversionSeeder');
            return;
        }

        // Get UOMs
        $pcs = UnitOfMeasure::where('company_id', $companyId)->where('code', 'pcs')->first();
        $box = UnitOfMeasure::where('company_id', $companyId)->where('code', 'box')->first();
        $pallet = UnitOfMeasure::where('company_id', $companyId)->where('code', 'pallet')->first();
        $L = UnitOfMeasure::where('company_id', $companyId)->where('code', 'L')->first();
        $drum = UnitOfMeasure::where('company_id', $companyId)->where('code', 'drum')->first();
        $kg = UnitOfMeasure::where('company_id', $companyId)->where('code', 'kg')->first();

        if (!$pcs || !$box) {
            $this->command->warn('Required UOMs not found, skipping ProductUomConversionSeeder');
            return;
        }

        $conversionsCreated = 0;

        // Get products from different categories for demo
        $products = Product::where('company_id', $companyId)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        foreach ($products as $index => $product) {
            $conversions = [];

            // Vary conversions based on product index for variety
            switch ($index % 5) {
                case 0:
                    // Small items: 1 box = 100 pcs, 1 pallet = 50 boxes
                    $conversions[] = [
                        'from_uom_id' => $box->id,
                        'to_uom_id' => $pcs->id,
                        'conversion_factor' => 100,
                        'is_default' => true,
                    ];
                    if ($pallet) {
                        $conversions[] = [
                            'from_uom_id' => $pallet->id,
                            'to_uom_id' => $box->id,
                            'conversion_factor' => 50,
                            'is_default' => true,
                        ];
                    }
                    break;

                case 1:
                    // Medium items: 1 box = 24 pcs, 1 pallet = 40 boxes
                    $conversions[] = [
                        'from_uom_id' => $box->id,
                        'to_uom_id' => $pcs->id,
                        'conversion_factor' => 24,
                        'is_default' => true,
                    ];
                    if ($pallet) {
                        $conversions[] = [
                            'from_uom_id' => $pallet->id,
                            'to_uom_id' => $box->id,
                            'conversion_factor' => 40,
                            'is_default' => true,
                        ];
                    }
                    break;

                case 2:
                    // Large items: 1 box = 6 pcs, 1 pallet = 24 boxes
                    $conversions[] = [
                        'from_uom_id' => $box->id,
                        'to_uom_id' => $pcs->id,
                        'conversion_factor' => 6,
                        'is_default' => true,
                    ];
                    if ($pallet) {
                        $conversions[] = [
                            'from_uom_id' => $pallet->id,
                            'to_uom_id' => $box->id,
                            'conversion_factor' => 24,
                            'is_default' => true,
                        ];
                    }
                    break;

                case 3:
                    // Very large items: 1 pallet = 4 pcs directly
                    if ($pallet) {
                        $conversions[] = [
                            'from_uom_id' => $pallet->id,
                            'to_uom_id' => $pcs->id,
                            'conversion_factor' => 4,
                            'is_default' => true,
                        ];
                    }
                    break;

                case 4:
                    // Liquids: 1 drum = 200 L
                    if ($drum && $L) {
                        $conversions[] = [
                            'from_uom_id' => $drum->id,
                            'to_uom_id' => $L->id,
                            'conversion_factor' => 200,
                            'is_default' => true,
                        ];
                    }
                    // Also add box conversion
                    $conversions[] = [
                        'from_uom_id' => $box->id,
                        'to_uom_id' => $pcs->id,
                        'conversion_factor' => 12,
                        'is_default' => true,
                    ];
                    break;
            }

            // Create conversions for this product
            foreach ($conversions as $conversionData) {
                // Check if conversion already exists
                $exists = ProductUomConversion::where('product_id', $product->id)
                    ->where('from_uom_id', $conversionData['from_uom_id'])
                    ->where('to_uom_id', $conversionData['to_uom_id'])
                    ->exists();

                if (!$exists) {
                    ProductUomConversion::create([
                        'company_id' => $companyId,
                        'product_id' => $product->id,
                        'from_uom_id' => $conversionData['from_uom_id'],
                        'to_uom_id' => $conversionData['to_uom_id'],
                        'conversion_factor' => $conversionData['conversion_factor'],
                        'is_default' => $conversionData['is_default'],
                        'is_active' => true,
                    ]);
                    $conversionsCreated++;
                }
            }
        }

        $this->command->info("Product UOM conversions seeded: {$conversionsCreated} conversions for " . $products->count() . " products");
    }
}
