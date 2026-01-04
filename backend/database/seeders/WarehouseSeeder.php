<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Warehouses in Netherlands
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        $warehouses = [
            // ========================================
            // MAIN FACILITIES
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Hoofdmagazijn Rotterdam',
                'code' => 'WH-MAIN',
                'warehouse_type' => 'finished_goods',
                'address' => 'Europaweg 245',
                'city' => 'Rotterdam',
                'country' => 'Netherlands',
                'postal_code' => '3199 LC',
                'contact_phone' => '+31-10-1234567',
                'contact_email' => 'hoofdmagazijn@agritech-nl.com',
                'contact_person' => 'Willem van der Berg',
                'is_active' => true,
                'is_default' => true,
                'settings' => [
                    'capacity' => 15000,
                    'area_sqm' => 8500,
                    'loading_docks' => 12,
                    'forklift_capacity' => '10 ton',
                ],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Production Plant Eindhoven',
                'code' => 'WH-PROD',
                'warehouse_type' => 'wip',
                'address' => 'Industrielaan 78',
                'city' => 'Eindhoven',
                'country' => 'Netherlands',
                'postal_code' => '5651 GH',
                'contact_phone' => '+31-40-2345678',
                'contact_email' => 'productie@agritech-nl.com',
                'contact_person' => 'Pieter de Vries',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 8000,
                    'area_sqm' => 12000,
                    'assembly_lines' => 4,
                    'paint_booth' => true,
                ],
            ],

            // ========================================
            // RAW MATERIALS STORAGE
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Steel & Metals Storage',
                'code' => 'WH-STEEL',
                'warehouse_type' => 'raw_materials',
                'address' => 'Havenweg 156',
                'city' => 'Rotterdam',
                'country' => 'Netherlands',
                'postal_code' => '3089 JK',
                'contact_phone' => '+31-10-3456789',
                'contact_email' => 'staal@agritech-nl.com',
                'contact_person' => 'Henk Janssen',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 5000,
                    'area_sqm' => 4000,
                    'crane_capacity' => '20 ton',
                    'covered' => true,
                ],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Components Warehouse',
                'code' => 'WH-COMP',
                'warehouse_type' => 'raw_materials',
                'address' => 'Techniekweg 45',
                'city' => 'Tilburg',
                'country' => 'Netherlands',
                'postal_code' => '5026 RM',
                'contact_phone' => '+31-13-4567890',
                'contact_email' => 'componenten@agritech-nl.com',
                'contact_person' => 'Johan Bakker',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 6000,
                    'area_sqm' => 3500,
                    'climate_controlled' => true,
                    'shelving_racks' => 450,
                ],
            ],

            // ========================================
            // DISTRIBUTION CENTERS
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Distribution Center North',
                'code' => 'DC-NORTH',
                'warehouse_type' => 'finished_goods',
                'address' => 'Noorderweg 89',
                'city' => 'Groningen',
                'country' => 'Netherlands',
                'postal_code' => '9723 CK',
                'contact_phone' => '+31-50-5678901',
                'contact_email' => 'dc-noord@agritech-nl.com',
                'contact_person' => 'Klaas Hoekstra',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 4000,
                    'area_sqm' => 2500,
                    'loading_docks' => 6,
                    'region' => 'North Netherlands',
                ],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Distribution Center South',
                'code' => 'DC-SOUTH',
                'warehouse_type' => 'finished_goods',
                'address' => 'Maasweg 234',
                'city' => 'Maastricht',
                'country' => 'Netherlands',
                'postal_code' => '6214 PP',
                'contact_phone' => '+31-43-6789012',
                'contact_email' => 'dc-zuid@agritech-nl.com',
                'contact_person' => 'Frans Willems',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 3500,
                    'area_sqm' => 2000,
                    'loading_docks' => 4,
                    'region' => 'South Netherlands / Belgium',
                ],
            ],

            // ========================================
            // SPARE PARTS CENTER
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Spare Parts Center',
                'code' => 'WH-PARTS',
                'warehouse_type' => 'finished_goods',
                'address' => 'Serviceweg 12',
                'city' => 'Utrecht',
                'country' => 'Netherlands',
                'postal_code' => '3542 AD',
                'contact_phone' => '+31-30-7890123',
                'contact_email' => 'onderdelen@agritech-nl.com',
                'contact_person' => 'Marianne Smit',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 25000,
                    'area_sqm' => 3000,
                    'bin_locations' => 15000,
                    'automated_picking' => true,
                ],
            ],

            // ========================================
            // QUALITY CONTROL ZONES
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Incoming Inspection Zone',
                'code' => 'QZ-INCOMING',
                'warehouse_type' => 'raw_materials',
                'address' => 'Kwaliteitsweg 1',
                'city' => 'Eindhoven',
                'country' => 'Netherlands',
                'postal_code' => '5651 GJ',
                'contact_phone' => '+31-40-8901234',
                'contact_email' => 'qc-incoming@agritech-nl.com',
                'contact_person' => 'QC Team',
                'is_active' => true,
                'is_default' => false,
                'is_quarantine_zone' => true,
                'is_rejection_zone' => false,
                'settings' => [
                    'capacity' => 1500,
                    'purpose' => 'Hold incoming materials pending QC inspection',
                    'inspection_bays' => 4,
                ],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Final Inspection Zone',
                'code' => 'QZ-FINAL',
                'warehouse_type' => 'finished_goods',
                'address' => 'Kwaliteitsweg 2',
                'city' => 'Eindhoven',
                'country' => 'Netherlands',
                'postal_code' => '5651 GJ',
                'contact_phone' => '+31-40-8901235',
                'contact_email' => 'qc-final@agritech-nl.com',
                'contact_person' => 'QC Team',
                'is_active' => true,
                'is_default' => false,
                'is_quarantine_zone' => true,
                'is_rejection_zone' => false,
                'settings' => [
                    'capacity' => 500,
                    'purpose' => 'Hold finished machinery pending final inspection',
                    'test_area' => true,
                ],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Rejection & NCR Zone',
                'code' => 'RZ-NCR',
                'warehouse_type' => 'returns',
                'address' => 'Kwaliteitsweg 3',
                'city' => 'Eindhoven',
                'country' => 'Netherlands',
                'postal_code' => '5651 GK',
                'contact_phone' => '+31-40-8901236',
                'contact_email' => 'ncr@agritech-nl.com',
                'contact_person' => 'QC Team',
                'is_active' => true,
                'is_default' => false,
                'is_quarantine_zone' => false,
                'is_rejection_zone' => true,
                'settings' => [
                    'capacity' => 800,
                    'purpose' => 'Hold non-conforming items for disposition',
                    'segregation_zones' => 3,
                ],
            ],

            // ========================================
            // SERVICE & RETURNS
            // ========================================
            [
                'company_id' => $companyId,
                'name' => 'Service & Returns Center',
                'code' => 'WH-SERVICE',
                'warehouse_type' => 'returns',
                'address' => 'Servicepark 56',
                'city' => 'Amersfoort',
                'country' => 'Netherlands',
                'postal_code' => '3824 MP',
                'contact_phone' => '+31-33-9012345',
                'contact_email' => 'service@agritech-nl.com',
                'contact_person' => 'Erik van Dam',
                'is_active' => true,
                'is_default' => false,
                'settings' => [
                    'capacity' => 2000,
                    'area_sqm' => 1500,
                    'repair_bays' => 8,
                    'purpose' => 'Warranty repairs and customer returns',
                ],
            ],
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::firstOrCreate(
                ['code' => $warehouseData['code'], 'company_id' => $companyId],
                $warehouseData
            );
        }

        // Link QC zones to production warehouse
        $prodWarehouse = Warehouse::where('code', 'WH-PROD')->first();
        $incomingQZ = Warehouse::where('code', 'QZ-INCOMING')->first();
        $finalQZ = Warehouse::where('code', 'QZ-FINAL')->first();
        $rejectionZone = Warehouse::where('code', 'RZ-NCR')->first();

        if ($prodWarehouse && $incomingQZ && $rejectionZone) {
            $prodWarehouse->update([
                'linked_quarantine_warehouse_id' => $incomingQZ->id,
                'linked_rejection_warehouse_id' => $rejectionZone->id,
            ]);
        }

        // Link QC zones to main warehouse
        $mainWarehouse = Warehouse::where('code', 'WH-MAIN')->first();
        if ($mainWarehouse && $finalQZ && $rejectionZone) {
            $mainWarehouse->update([
                'linked_quarantine_warehouse_id' => $finalQZ->id,
                'linked_rejection_warehouse_id' => $rejectionZone->id,
            ]);
        }

        $this->command->info('Netherlands warehouses seeded: ' . count($warehouses) . ' locations (including QC zones)');
    }
}
