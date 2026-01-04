<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Attributes
     */
    public function run(): void
    {
        // Get default company
        $company = Company::first();
        $companyId = $company?->id;

        $attributes = [
            // ========================================
            // VARIANT ATTRIBUTES (for product variants)
            // ========================================
            [
                'name' => 'engine_power',
                'display_name' => 'Engine Power',
                'type' => 'select',
                'order' => 1,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Engine power in horsepower (HP)',
                'values' => [
                    ['value' => '25 HP', 'order' => 1],
                    ['value' => '35 HP', 'order' => 2],
                    ['value' => '50 HP', 'order' => 3],
                    ['value' => '75 HP', 'order' => 4],
                    ['value' => '100 HP', 'order' => 5],
                    ['value' => '120 HP', 'order' => 6],
                    ['value' => '150 HP', 'order' => 7],
                    ['value' => '180 HP', 'order' => 8],
                    ['value' => '200 HP', 'order' => 9],
                    ['value' => '250 HP', 'order' => 10],
                    ['value' => '300 HP', 'order' => 11],
                    ['value' => '400 HP', 'order' => 12],
                ]
            ],
            [
                'name' => 'working_width',
                'display_name' => 'Working Width',
                'type' => 'select',
                'order' => 2,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Working width in meters',
                'values' => [
                    ['value' => '1.5 m', 'order' => 1],
                    ['value' => '2.0 m', 'order' => 2],
                    ['value' => '2.5 m', 'order' => 3],
                    ['value' => '3.0 m', 'order' => 4],
                    ['value' => '4.0 m', 'order' => 5],
                    ['value' => '5.0 m', 'order' => 6],
                    ['value' => '6.0 m', 'order' => 7],
                    ['value' => '8.0 m', 'order' => 8],
                    ['value' => '10.0 m', 'order' => 9],
                    ['value' => '12.0 m', 'order' => 10],
                ]
            ],
            [
                'name' => 'tank_capacity',
                'display_name' => 'Tank Capacity',
                'type' => 'select',
                'order' => 3,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Tank/hopper capacity in liters',
                'values' => [
                    ['value' => '200 L', 'order' => 1],
                    ['value' => '400 L', 'order' => 2],
                    ['value' => '600 L', 'order' => 3],
                    ['value' => '800 L', 'order' => 4],
                    ['value' => '1000 L', 'order' => 5],
                    ['value' => '1500 L', 'order' => 6],
                    ['value' => '2000 L', 'order' => 7],
                    ['value' => '3000 L', 'order' => 8],
                    ['value' => '4000 L', 'order' => 9],
                    ['value' => '5000 L', 'order' => 10],
                ]
            ],
            [
                'name' => 'number_of_rows',
                'display_name' => 'Number of Rows',
                'type' => 'select',
                'order' => 4,
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Number of planting/harvesting rows',
                'values' => [
                    ['value' => '2 Rows', 'order' => 1],
                    ['value' => '4 Rows', 'order' => 2],
                    ['value' => '6 Rows', 'order' => 3],
                    ['value' => '8 Rows', 'order' => 4],
                    ['value' => '12 Rows', 'order' => 5],
                    ['value' => '16 Rows', 'order' => 6],
                    ['value' => '24 Rows', 'order' => 7],
                ]
            ],

            // ========================================
            // SPECIFICATION ATTRIBUTES
            // ========================================
            [
                'name' => 'brand',
                'display_name' => 'Brand',
                'type' => 'select',
                'order' => 5,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Manufacturer brand',
                'values' => [
                    ['value' => 'AgriTech NL', 'order' => 1],
                    ['value' => 'HollandAgro', 'order' => 2],
                    ['value' => 'EuroFarm', 'order' => 3],
                    ['value' => 'Deutz-Fahr', 'order' => 4],
                    ['value' => 'Kverneland', 'order' => 5],
                    ['value' => 'Lemken', 'order' => 6],
                    ['value' => 'Amazone', 'order' => 7],
                    ['value' => 'Grimme', 'order' => 8],
                    ['value' => 'Lely', 'order' => 9],
                    ['value' => 'Priva', 'order' => 10],
                ]
            ],
            [
                'name' => 'transmission_type',
                'display_name' => 'Transmission Type',
                'type' => 'select',
                'order' => 6,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Type of transmission system',
                'values' => [
                    ['value' => 'Manual', 'order' => 1],
                    ['value' => 'Synchro Shuttle', 'order' => 2],
                    ['value' => 'Power Shuttle', 'order' => 3],
                    ['value' => 'CVT', 'order' => 4],
                    ['value' => 'Powershift', 'order' => 5],
                    ['value' => 'Hydrostatic', 'order' => 6],
                ]
            ],
            [
                'name' => 'drive_type',
                'display_name' => 'Drive Type',
                'type' => 'select',
                'order' => 7,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Drive configuration',
                'values' => [
                    ['value' => '2WD', 'order' => 1],
                    ['value' => '4WD', 'order' => 2],
                    ['value' => 'MFWD', 'order' => 3],
                    ['value' => 'Track', 'order' => 4],
                ]
            ],
            [
                'name' => 'hydraulic_flow',
                'display_name' => 'Hydraulic Flow',
                'type' => 'select',
                'order' => 8,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Hydraulic pump flow rate',
                'values' => [
                    ['value' => '40 L/min', 'order' => 1],
                    ['value' => '60 L/min', 'order' => 2],
                    ['value' => '80 L/min', 'order' => 3],
                    ['value' => '100 L/min', 'order' => 4],
                    ['value' => '120 L/min', 'order' => 5],
                    ['value' => '150 L/min', 'order' => 6],
                ]
            ],
            [
                'name' => 'pto_speed',
                'display_name' => 'PTO Speed',
                'type' => 'select',
                'order' => 9,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Power Take-Off speed options',
                'values' => [
                    ['value' => '540 RPM', 'order' => 1],
                    ['value' => '540/1000 RPM', 'order' => 2],
                    ['value' => '1000 RPM', 'order' => 3],
                    ['value' => '540E/1000E RPM', 'order' => 4],
                ]
            ],
            [
                'name' => 'lift_capacity',
                'display_name' => 'Lift Capacity',
                'type' => 'select',
                'order' => 10,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Rear lift capacity in kg',
                'values' => [
                    ['value' => '1500 kg', 'order' => 1],
                    ['value' => '2500 kg', 'order' => 2],
                    ['value' => '3500 kg', 'order' => 3],
                    ['value' => '5000 kg', 'order' => 4],
                    ['value' => '7000 kg', 'order' => 5],
                    ['value' => '9000 kg', 'order' => 6],
                    ['value' => '11000 kg', 'order' => 7],
                ]
            ],
            [
                'name' => 'warranty',
                'display_name' => 'Warranty',
                'type' => 'select',
                'order' => 11,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Warranty period',
                'values' => [
                    ['value' => '1 Year', 'order' => 1],
                    ['value' => '2 Years', 'order' => 2],
                    ['value' => '3 Years', 'order' => 3],
                    ['value' => '5 Years', 'order' => 4],
                    ['value' => '2000 Hours', 'order' => 5],
                    ['value' => '3000 Hours', 'order' => 6],
                ]
            ],
            [
                'name' => 'material',
                'display_name' => 'Material',
                'type' => 'select',
                'order' => 12,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Primary construction material',
                'values' => [
                    ['value' => 'Steel', 'order' => 1],
                    ['value' => 'Stainless Steel', 'order' => 2],
                    ['value' => 'Hardox Steel', 'order' => 3],
                    ['value' => 'Cast Iron', 'order' => 4],
                    ['value' => 'Aluminum', 'order' => 5],
                    ['value' => 'Polyethylene', 'order' => 6],
                    ['value' => 'Composite', 'order' => 7],
                ]
            ],
            [
                'name' => 'hitch_type',
                'display_name' => 'Hitch Type',
                'type' => 'select',
                'order' => 13,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Attachment hitch type',
                'values' => [
                    ['value' => '3-Point Cat I', 'order' => 1],
                    ['value' => '3-Point Cat II', 'order' => 2],
                    ['value' => '3-Point Cat III', 'order' => 3],
                    ['value' => 'Drawbar', 'order' => 4],
                    ['value' => 'Quick Hitch', 'order' => 5],
                    ['value' => 'Front Loader', 'order' => 6],
                ]
            ],
            [
                'name' => 'automation_level',
                'display_name' => 'Automation Level',
                'type' => 'select',
                'order' => 14,
                'is_variant_attribute' => false,
                'is_filterable' => true,
                'is_visible' => true,
                'is_required' => false,
                'description' => 'Level of automation/precision farming',
                'values' => [
                    ['value' => 'Manual', 'order' => 1],
                    ['value' => 'Semi-Automatic', 'order' => 2],
                    ['value' => 'GPS Ready', 'order' => 3],
                    ['value' => 'GPS Guided', 'order' => 4],
                    ['value' => 'ISOBUS Compatible', 'order' => 5],
                    ['value' => 'Fully Autonomous', 'order' => 6],
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

        $this->command->info('Agricultural Machinery attribute seeding completed!');
    }
}
