<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        $attributes = [
            [
                'name' => 'color',
                'display_name' => 'Color',
                'type' => 'select',
                'order' => 1,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Product color',
                'values' => [
                    ['value' => 'Black', 'order' => 1],
                    ['value' => 'White', 'order' => 2],
                    ['value' => 'Gray', 'order' => 3],
                    ['value' => 'Blue', 'order' => 4],
                    ['value' => 'Red', 'order' => 5],
                    ['value' => 'Green', 'order' => 6],
                    ['value' => 'Yellow', 'order' => 7],
                    ['value' => 'Orange', 'order' => 8],
                    ['value' => 'Purple', 'order' => 9],
                    ['value' => 'Pink', 'order' => 10],
                    ['value' => 'Brown', 'order' => 11],
                    ['value' => 'Navy', 'order' => 12],
                ]
            ],
            [
                'name' => 'size',
                'display_name' => 'Size',
                'type' => 'select',
                'order' => 2,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Product size',
                'values' => [
                    ['value' => 'XS', 'order' => 1],
                    ['value' => 'S', 'order' => 2],
                    ['value' => 'M', 'order' => 3],
                    ['value' => 'L', 'order' => 4],
                    ['value' => 'XL', 'order' => 5],
                    ['value' => 'XXL', 'order' => 6],
                    ['value' => 'XXXL', 'order' => 7],
                ]
            ],
            [
                'name' => 'storage',
                'display_name' => 'Storage',
                'type' => 'select',
                'order' => 3,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Storage capacity',
                'values' => [
                    ['value' => '32GB', 'order' => 1],
                    ['value' => '64GB', 'order' => 2],
                    ['value' => '128GB', 'order' => 3],
                    ['value' => '256GB', 'order' => 4],
                    ['value' => '512GB', 'order' => 5],
                    ['value' => '1TB', 'order' => 6],
                    ['value' => '2TB', 'order' => 7],
                ]
            ],
            [
                'name' => 'ram',
                'display_name' => 'RAM',
                'type' => 'select',
                'order' => 4,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'RAM capacity',
                'values' => [
                    ['value' => '4GB', 'order' => 1],
                    ['value' => '6GB', 'order' => 2],
                    ['value' => '8GB', 'order' => 3],
                    ['value' => '12GB', 'order' => 4],
                    ['value' => '16GB', 'order' => 5],
                    ['value' => '32GB', 'order' => 6],
                ]
            ],
            [
                'name' => 'material',
                'display_name' => 'Material',
                'type' => 'select',
                'order' => 5,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Product material',
                'values' => [
                    ['value' => 'Cotton', 'order' => 1],
                    ['value' => 'Polyester', 'order' => 2],
                    ['value' => 'Leather', 'order' => 3],
                    ['value' => 'Metal', 'order' => 4],
                    ['value' => 'Plastic', 'order' => 5],
                    ['value' => 'Wood', 'order' => 6],
                    ['value' => 'Glass', 'order' => 7],
                ]
            ],
            [
                'name' => 'brand',
                'display_name' => 'Brand',
                'type' => 'select',
                'order' => 6,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Product brand',
                'values' => [
                    ['value' => 'Apple', 'order' => 1],
                    ['value' => 'Samsung', 'order' => 2],
                    ['value' => 'Huawei', 'order' => 3],
                    ['value' => 'Xiaomi', 'order' => 4],
                    ['value' => 'Sony', 'order' => 5],
                    ['value' => 'LG', 'order' => 6],
                    ['value' => 'Asus', 'order' => 7],
                    ['value' => 'Lenovo', 'order' => 8],
                    ['value' => 'Dell', 'order' => 9],
                    ['value' => 'HP', 'order' => 10],
                ]
            ],
            [
                'name' => 'warranty',
                'display_name' => 'Warranty',
                'type' => 'select',
                'order' => 7,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Warranty period',
                'values' => [
                    ['value' => '6 Months', 'order' => 1],
                    ['value' => '1 Year', 'order' => 2],
                    ['value' => '2 Years', 'order' => 3],
                    ['value' => '3 Years', 'order' => 4],
                    ['value' => '5 Years', 'order' => 5],
                ]
            ],
            [
                'name' => 'screen_size',
                'display_name' => 'Screen Size',
                'type' => 'select',
                'order' => 8,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Screen size',
                'values' => [
                    ['value' => '5.5"', 'order' => 1],
                    ['value' => '6.0"', 'order' => 2],
                    ['value' => '6.1"', 'order' => 3],
                    ['value' => '6.5"', 'order' => 4],
                    ['value' => '6.7"', 'order' => 5],
                    ['value' => '10.2"', 'order' => 6],
                    ['value' => '11"', 'order' => 7],
                    ['value' => '12.9"', 'order' => 8],
                    ['value' => '13"', 'order' => 9],
                    ['value' => '15"', 'order' => 10],
                    ['value' => '17"', 'order' => 11],
                ]
            ],
        ];

        foreach ($attributes as $attributeData) {
            $values = $attributeData['values'] ?? [];
            unset($attributeData['values']);

            // Add company_id
            $attributeData['company_id'] = $companyId;

            // Create attribute
            $attribute = Attribute::create($attributeData);

            // Create values
            foreach ($values as $valueData) {
                $attribute->values()->create($valueData);
            }

            $this->command->info("Created attribute: {$attribute->display_name} with " . count($values) . " values");
        }

        $this->command->info('Attribute seeding completed!');
    }
}
