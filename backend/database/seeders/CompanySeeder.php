<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Demo Company',
                'legal_name' => 'Demo Company Ltd.',
                'tax_id' => 'DEMO-TAX-001',
                'email' => 'info@demo-company.com',
                'phone' => '+1-555-0100',
                'address' => '123 Business Street',
                'city' => 'New York',
                'country' => 'USA',
                'postal_code' => '10001',
                'base_currency' => 'USD',
                'supported_currencies' => ['USD', 'EUR', 'TRY'],
                'timezone' => 'America/New_York',
                'settings' => [
                    'low_stock_alert' => true,
                    'auto_reorder' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'AgriTech Netherlands B.V.',
                'legal_name' => 'AgriTech Netherlands Besloten Vennootschap',
                'tax_id' => 'NL123456789B01',
                'email' => 'info@agritech-nl.com',
                'phone' => '+31-20-1234567',
                'address' => 'Europaweg 245',
                'city' => 'Rotterdam',
                'country' => 'Netherlands',
                'postal_code' => '3199 LC',
                'base_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'USD', 'GBP'],
                'timezone' => 'Europe/Amsterdam',
                'settings' => [
                    'low_stock_alert' => true,
                    'auto_reorder' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Global Manufacturing Inc.',
                'legal_name' => 'Global Manufacturing Incorporated',
                'tax_id' => 'GM-TAX-2024',
                'email' => 'contact@globalmfg.com',
                'phone' => '+44-20-7890123',
                'address' => 'Industrial Park 789',
                'city' => 'London',
                'country' => 'United Kingdom',
                'postal_code' => 'SW1A 1AA',
                'base_currency' => 'GBP',
                'supported_currencies' => ['GBP', 'EUR', 'USD'],
                'timezone' => 'Europe/London',
                'settings' => [
                    'low_stock_alert' => true,
                    'auto_reorder' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'TechSolutions GmbH',
                'legal_name' => 'TechSolutions Gesellschaft mit beschränkter Haftung',
                'tax_id' => 'DE987654321',
                'email' => 'info@techsolutions.de',
                'phone' => '+49-30-4567890',
                'address' => 'Technologiepark 12',
                'city' => 'Berlin',
                'country' => 'Germany',
                'postal_code' => '10115',
                'base_currency' => 'EUR',
                'supported_currencies' => ['EUR', 'USD', 'CHF'],
                'timezone' => 'Europe/Berlin',
                'settings' => [
                    'low_stock_alert' => true,
                    'auto_reorder' => false,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($companies as $companyData) {
            $company = Company::firstOrCreate(
                ['name' => $companyData['name']],
                $companyData
            );

            $this->command->info("Company created: {$company->name} (ID: {$company->id})");
        }

        $this->command->info('Total companies: ' . Company::count());
    }
}
