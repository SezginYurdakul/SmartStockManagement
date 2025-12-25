<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        $productTypes = [
            [
                'company_id' => $companyId,
                'name' => 'Finished Goods',
                'code' => 'FG',
                'description' => 'Products ready for sale to customers',
                'track_inventory' => true,
                'can_be_purchased' => false,
                'can_be_sold' => true,
                'can_be_manufactured' => true,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Raw Materials',
                'code' => 'RM',
                'description' => 'Materials used in manufacturing',
                'track_inventory' => true,
                'can_be_purchased' => true,
                'can_be_sold' => false,
                'can_be_manufactured' => false,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Work in Progress',
                'code' => 'WIP',
                'description' => 'Products currently being manufactured',
                'track_inventory' => true,
                'can_be_purchased' => false,
                'can_be_sold' => false,
                'can_be_manufactured' => true,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Services',
                'code' => 'SVC',
                'description' => 'Non-physical services offered',
                'track_inventory' => false,
                'can_be_purchased' => true,
                'can_be_sold' => true,
                'can_be_manufactured' => false,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Consumables',
                'code' => 'CON',
                'description' => 'Items consumed during operations',
                'track_inventory' => true,
                'can_be_purchased' => true,
                'can_be_sold' => false,
                'can_be_manufactured' => false,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Spare Parts',
                'code' => 'SP',
                'description' => 'Replacement parts for equipment',
                'track_inventory' => true,
                'can_be_purchased' => true,
                'can_be_sold' => true,
                'can_be_manufactured' => false,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Packaging Materials',
                'code' => 'PKG',
                'description' => 'Materials used for product packaging',
                'track_inventory' => true,
                'can_be_purchased' => true,
                'can_be_sold' => false,
                'can_be_manufactured' => false,
                'is_active' => true,
            ],
        ];

        foreach ($productTypes as $typeData) {
            ProductType::firstOrCreate(
                ['code' => $typeData['code'], 'company_id' => $companyId],
                $typeData
            );
        }

        $this->command->info('Product types seeded: ' . count($productTypes) . ' types');
    }
}
