<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        $warehouses = [
            [
                'company_id' => $companyId,
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN',
                'warehouse_type' => 'finished_goods',
                'address' => '123 Industrial Park, Building A',
                'city' => 'New York',
                'country' => 'USA',
                'postal_code' => '10001',
                'contact_phone' => '+1-555-0101',
                'contact_email' => 'main-warehouse@demo-company.com',
                'contact_person' => 'John Smith',
                'is_active' => true,
                'is_default' => true,
                'settings' => ['capacity' => 10000],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Distribution Center East',
                'code' => 'DC-EAST',
                'warehouse_type' => 'finished_goods',
                'address' => '456 Logistics Avenue',
                'city' => 'Boston',
                'country' => 'USA',
                'postal_code' => '02101',
                'contact_phone' => '+1-555-0102',
                'contact_email' => 'dc-east@demo-company.com',
                'contact_person' => 'Sarah Johnson',
                'is_active' => true,
                'is_default' => false,
                'settings' => ['capacity' => 5000],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Distribution Center West',
                'code' => 'DC-WEST',
                'warehouse_type' => 'finished_goods',
                'address' => '789 Commerce Street',
                'city' => 'Los Angeles',
                'country' => 'USA',
                'postal_code' => '90001',
                'contact_phone' => '+1-555-0103',
                'contact_email' => 'dc-west@demo-company.com',
                'contact_person' => 'Mike Davis',
                'is_active' => true,
                'is_default' => false,
                'settings' => ['capacity' => 5000],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Raw Materials Storage',
                'code' => 'WH-RAW',
                'warehouse_type' => 'raw_materials',
                'address' => '321 Supply Chain Road',
                'city' => 'Chicago',
                'country' => 'USA',
                'postal_code' => '60601',
                'contact_phone' => '+1-555-0104',
                'contact_email' => 'raw-materials@demo-company.com',
                'contact_person' => 'Emily Brown',
                'is_active' => true,
                'is_default' => false,
                'settings' => ['capacity' => 3000],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Returns Processing Center',
                'code' => 'WH-RET',
                'warehouse_type' => 'returns',
                'address' => '555 Return Lane',
                'city' => 'Dallas',
                'country' => 'USA',
                'postal_code' => '75201',
                'contact_phone' => '+1-555-0105',
                'contact_email' => 'returns@demo-company.com',
                'contact_person' => 'Tom Wilson',
                'is_active' => true,
                'is_default' => false,
                'settings' => ['capacity' => 2000],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Work in Progress Facility',
                'code' => 'WH-WIP',
                'warehouse_type' => 'wip',
                'address' => '777 Manufacturing Blvd',
                'city' => 'Detroit',
                'country' => 'USA',
                'postal_code' => '48201',
                'contact_phone' => '+1-555-0106',
                'contact_email' => 'wip@demo-company.com',
                'contact_person' => 'Lisa Anderson',
                'is_active' => true,
                'is_default' => false,
                'settings' => ['capacity' => 4000],
            ],
            // QC Zones
            [
                'company_id' => $companyId,
                'name' => 'Quarantine Zone',
                'code' => 'QZ-01',
                'warehouse_type' => 'raw_materials',
                'address' => '888 Quality Control Road',
                'city' => 'New York',
                'country' => 'USA',
                'postal_code' => '10002',
                'contact_phone' => '+1-555-0107',
                'contact_email' => 'quarantine@demo-company.com',
                'contact_person' => 'Quality Team',
                'is_active' => true,
                'is_default' => false,
                'is_quarantine_zone' => true,
                'is_rejection_zone' => false,
                'settings' => ['capacity' => 1000, 'purpose' => 'Hold items pending inspection'],
            ],
            [
                'company_id' => $companyId,
                'name' => 'Rejection Zone',
                'code' => 'RZ-01',
                'warehouse_type' => 'returns',
                'address' => '999 Quality Control Road',
                'city' => 'New York',
                'country' => 'USA',
                'postal_code' => '10003',
                'contact_phone' => '+1-555-0108',
                'contact_email' => 'rejection@demo-company.com',
                'contact_person' => 'Quality Team',
                'is_active' => true,
                'is_default' => false,
                'is_quarantine_zone' => false,
                'is_rejection_zone' => true,
                'settings' => ['capacity' => 500, 'purpose' => 'Hold rejected items for disposal or return'],
            ],
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::firstOrCreate(
                ['code' => $warehouseData['code'], 'company_id' => $companyId],
                $warehouseData
            );
        }

        // Link QC zones to main warehouse
        $mainWarehouse = Warehouse::where('code', 'WH-MAIN')->first();
        $quarantineZone = Warehouse::where('code', 'QZ-01')->first();
        $rejectionZone = Warehouse::where('code', 'RZ-01')->first();

        if ($mainWarehouse && $quarantineZone && $rejectionZone) {
            $mainWarehouse->update([
                'linked_quarantine_warehouse_id' => $quarantineZone->id,
                'linked_rejection_warehouse_id' => $rejectionZone->id,
            ]);
        }

        $this->command->info('Warehouses seeded: ' . count($warehouses) . ' warehouses (including QC zones)');
    }
}
