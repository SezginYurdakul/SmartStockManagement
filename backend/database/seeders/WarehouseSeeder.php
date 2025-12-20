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
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::firstOrCreate(
                ['code' => $warehouseData['code'], 'company_id' => $companyId],
                $warehouseData
            );
        }

        $this->command->info('Warehouses seeded: ' . count($warehouses) . ' warehouses');
    }
}
