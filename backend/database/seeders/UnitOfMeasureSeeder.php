<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        // First create base units (without base_unit_id)
        $baseUnits = [
            // Quantity base
            [
                'company_id' => $companyId,
                'code' => 'pcs',
                'name' => 'Piece',
                'uom_type' => 'quantity',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],
            // Weight base
            [
                'company_id' => $companyId,
                'code' => 'g',
                'name' => 'Gram',
                'uom_type' => 'weight',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 2,
                'is_active' => true,
            ],
            // Length base
            [
                'company_id' => $companyId,
                'code' => 'm',
                'name' => 'Meter',
                'uom_type' => 'length',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 2,
                'is_active' => true,
            ],
            // Volume base
            [
                'company_id' => $companyId,
                'code' => 'L',
                'name' => 'Liter',
                'uom_type' => 'volume',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 2,
                'is_active' => true,
            ],
            // Area base
            [
                'company_id' => $companyId,
                'code' => 'm2',
                'name' => 'Square Meter',
                'uom_type' => 'area',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 2,
                'is_active' => true,
            ],
        ];

        $createdUnits = [];
        foreach ($baseUnits as $unitData) {
            $unit = UnitOfMeasure::firstOrCreate(
                ['code' => $unitData['code'], 'company_id' => $companyId],
                $unitData
            );
            $createdUnits[$unitData['code']] = $unit->id;
        }

        // Now create derived units with base_unit_id
        $derivedUnits = [
            // Quantity units
            [
                'company_id' => $companyId,
                'code' => 'dz',
                'name' => 'Dozen',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 12,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'box',
                'name' => 'Box',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'ctn',
                'name' => 'Carton',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],

            // Weight units
            [
                'company_id' => $companyId,
                'code' => 'kg',
                'name' => 'Kilogram',
                'uom_type' => 'weight',
                'base_unit_id' => $createdUnits['g'],
                'conversion_factor' => 1000,
                'precision' => 3,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 't',
                'name' => 'Ton',
                'uom_type' => 'weight',
                'base_unit_id' => $createdUnits['g'],
                'conversion_factor' => 1000000,
                'precision' => 3,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'lb',
                'name' => 'Pound',
                'uom_type' => 'weight',
                'base_unit_id' => $createdUnits['g'],
                'conversion_factor' => 453.592,
                'precision' => 3,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'oz',
                'name' => 'Ounce',
                'uom_type' => 'weight',
                'base_unit_id' => $createdUnits['g'],
                'conversion_factor' => 28.3495,
                'precision' => 3,
                'is_active' => true,
            ],

            // Length units
            [
                'company_id' => $companyId,
                'code' => 'cm',
                'name' => 'Centimeter',
                'uom_type' => 'length',
                'base_unit_id' => $createdUnits['m'],
                'conversion_factor' => 0.01,
                'precision' => 2,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'mm',
                'name' => 'Millimeter',
                'uom_type' => 'length',
                'base_unit_id' => $createdUnits['m'],
                'conversion_factor' => 0.001,
                'precision' => 2,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'in',
                'name' => 'Inch',
                'uom_type' => 'length',
                'base_unit_id' => $createdUnits['m'],
                'conversion_factor' => 0.0254,
                'precision' => 2,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'ft',
                'name' => 'Foot',
                'uom_type' => 'length',
                'base_unit_id' => $createdUnits['m'],
                'conversion_factor' => 0.3048,
                'precision' => 2,
                'is_active' => true,
            ],

            // Volume units
            [
                'company_id' => $companyId,
                'code' => 'mL',
                'name' => 'Milliliter',
                'uom_type' => 'volume',
                'base_unit_id' => $createdUnits['L'],
                'conversion_factor' => 0.001,
                'precision' => 2,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'gal',
                'name' => 'Gallon',
                'uom_type' => 'volume',
                'base_unit_id' => $createdUnits['L'],
                'conversion_factor' => 3.78541,
                'precision' => 2,
                'is_active' => true,
            ],

            // Area units
            [
                'company_id' => $companyId,
                'code' => 'ft2',
                'name' => 'Square Foot',
                'uom_type' => 'area',
                'base_unit_id' => $createdUnits['m2'],
                'conversion_factor' => 0.092903,
                'precision' => 2,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'ha',
                'name' => 'Hectare',
                'uom_type' => 'area',
                'base_unit_id' => $createdUnits['m2'],
                'conversion_factor' => 10000,
                'precision' => 2,
                'is_active' => true,
            ],

            // Power units (for agricultural machinery)
            [
                'company_id' => $companyId,
                'code' => 'hp',
                'name' => 'Horsepower',
                'uom_type' => 'power',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'kW',
                'name' => 'Kilowatt',
                'uom_type' => 'power',
                'base_unit_id' => null,
                'conversion_factor' => 1.341, // 1 kW = 1.341 HP
                'precision' => 2,
                'is_active' => true,
            ],

            // Speed/Flow units (for machinery)
            [
                'company_id' => $companyId,
                'code' => 'rpm',
                'name' => 'Revolutions per Minute',
                'uom_type' => 'speed',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'L/min',
                'name' => 'Liters per Minute',
                'uom_type' => 'flow',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 1,
                'is_active' => true,
            ],

            // Time units (for service/warranty)
            [
                'company_id' => $companyId,
                'code' => 'hr',
                'name' => 'Hour',
                'uom_type' => 'time',
                'base_unit_id' => null,
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],

            // Set units (for machinery sets/kits)
            [
                'company_id' => $companyId,
                'code' => 'set',
                'name' => 'Set',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 1,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'pair',
                'name' => 'Pair',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 2,
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'pallet',
                'name' => 'Pallet',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 1, // Product-specific, default 1
                'precision' => 0,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'code' => 'drum',
                'name' => 'Drum',
                'uom_type' => 'quantity',
                'base_unit_id' => $createdUnits['pcs'],
                'conversion_factor' => 1, // Product-specific, default 1
                'precision' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($derivedUnits as $unitData) {
            UnitOfMeasure::firstOrCreate(
                ['code' => $unitData['code'], 'company_id' => $companyId],
                $unitData
            );
        }

        $totalUnits = count($baseUnits) + count($derivedUnits);
        $this->command->info("Units of measure seeded: {$totalUnits} units");
    }
}
