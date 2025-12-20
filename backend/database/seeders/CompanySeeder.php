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
        // Create default company
        $company = Company::firstOrCreate(
            ['name' => 'Demo Company'],
            [
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
            ]
        );

        $this->command->info("Demo company created: {$company->name} (ID: {$company->id})");
    }
}
