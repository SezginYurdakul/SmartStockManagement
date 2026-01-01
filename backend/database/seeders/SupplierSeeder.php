<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $user = User::where('company_id', $company->id)->first();

        $suppliers = [
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00001',
                'name' => 'Tech Components Ltd.',
                'legal_name' => 'Tech Components Limited',
                'tax_id' => 'TC123456789',
                'email' => 'orders@techcomponents.com',
                'phone' => '+1-555-0101',
                'website' => 'https://techcomponents.com',
                'address' => '123 Industrial Park',
                'city' => 'San Francisco',
                'state' => 'California',
                'country' => 'USA',
                'postal_code' => '94102',
                'contact_person' => 'John Smith',
                'contact_email' => 'john.smith@techcomponents.com',
                'contact_phone' => '+1-555-0102',
                'currency' => 'USD',
                'payment_terms_days' => 30,
                'credit_limit' => 50000.00,
                'lead_time_days' => 7,
                'minimum_order_amount' => 500.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00002',
                'name' => 'Global Electronics Inc.',
                'legal_name' => 'Global Electronics Incorporated',
                'tax_id' => 'GE987654321',
                'email' => 'sales@globalelectronics.com',
                'phone' => '+1-555-0201',
                'website' => 'https://globalelectronics.com',
                'address' => '456 Commerce Way',
                'city' => 'New York',
                'state' => 'New York',
                'country' => 'USA',
                'postal_code' => '10001',
                'contact_person' => 'Jane Doe',
                'contact_email' => 'jane.doe@globalelectronics.com',
                'contact_phone' => '+1-555-0202',
                'currency' => 'USD',
                'payment_terms_days' => 45,
                'credit_limit' => 100000.00,
                'lead_time_days' => 14,
                'minimum_order_amount' => 1000.00,
                'rating' => 4,
                'is_active' => true,
                'created_by' => $user->id,
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00003',
                'name' => 'Euro Parts GmbH',
                'legal_name' => 'Euro Parts GmbH',
                'tax_id' => 'DE123456789',
                'email' => 'orders@europarts.de',
                'phone' => '+49-30-12345678',
                'website' => 'https://europarts.de',
                'address' => 'Industriestrasse 42',
                'city' => 'Berlin',
                'state' => 'Berlin',
                'country' => 'Germany',
                'postal_code' => '10115',
                'contact_person' => 'Hans Mueller',
                'contact_email' => 'h.mueller@europarts.de',
                'contact_phone' => '+49-30-12345679',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 75000.00,
                'lead_time_days' => 21,
                'minimum_order_amount' => 2000.00,
                'rating' => 4,
                'is_active' => true,
                'created_by' => $user->id,
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00004',
                'name' => 'Asia Manufacturing Co.',
                'legal_name' => 'Asia Manufacturing Company Ltd.',
                'tax_id' => 'CN987654321',
                'email' => 'export@asiamfg.cn',
                'phone' => '+86-21-12345678',
                'website' => 'https://asiamfg.cn',
                'address' => '789 Export Zone',
                'city' => 'Shanghai',
                'state' => 'Shanghai',
                'country' => 'China',
                'postal_code' => '200000',
                'contact_person' => 'Li Wei',
                'contact_email' => 'li.wei@asiamfg.cn',
                'contact_phone' => '+86-21-12345679',
                'currency' => 'USD',
                'payment_terms_days' => 60,
                'credit_limit' => 200000.00,
                'lead_time_days' => 45,
                'minimum_order_amount' => 5000.00,
                'shipping_method' => 'Sea Freight',
                'rating' => 3,
                'is_active' => true,
                'created_by' => $user->id,
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00005',
                'name' => 'Metro Distribution Ltd',
                'legal_name' => 'Metro Distribution Limited',
                'tax_id' => 'GB123456789',
                'email' => 'orders@metrodist.co.uk',
                'phone' => '+44-20-7123456',
                'website' => 'https://metrodist.co.uk',
                'address' => '15 Industrial Park Road',
                'city' => 'London',
                'state' => 'Greater London',
                'country' => 'United Kingdom',
                'postal_code' => 'E14 5AB',
                'contact_person' => 'James Wilson',
                'contact_email' => 'james.wilson@metrodist.co.uk',
                'contact_phone' => '+44-7700-123456',
                'currency' => 'GBP',
                'payment_terms_days' => 15,
                'credit_limit' => 500000.00,
                'lead_time_days' => 3,
                'minimum_order_amount' => 1000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        // Attach some products to suppliers
        $products = Product::where('company_id', $company->id)->limit(5)->get();
        $suppliers = Supplier::where('company_id', $company->id)->get();

        if ($products->count() > 0 && $suppliers->count() > 0) {
            // Attach first 3 products to first supplier
            $suppliers[0]->products()->attach($products[0]->id, [
                'supplier_sku' => 'TC-' . $products[0]->sku,
                'unit_price' => 10.50,
                'currency' => 'USD',
                'minimum_order_qty' => 100,
                'lead_time_days' => 5,
                'is_preferred' => true,
                'is_active' => true,
            ]);

            if (isset($products[1])) {
                $suppliers[0]->products()->attach($products[1]->id, [
                    'supplier_sku' => 'TC-' . $products[1]->sku,
                    'unit_price' => 25.00,
                    'currency' => 'USD',
                    'minimum_order_qty' => 50,
                    'lead_time_days' => 7,
                    'is_preferred' => true,
                    'is_active' => true,
                ]);
            }

            // Attach same products to second supplier with different prices
            if (isset($suppliers[1])) {
                $suppliers[1]->products()->attach($products[0]->id, [
                    'supplier_sku' => 'GE-' . $products[0]->sku,
                    'unit_price' => 11.00,
                    'currency' => 'USD',
                    'minimum_order_qty' => 200,
                    'lead_time_days' => 10,
                    'is_preferred' => false,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Suppliers seeded successfully!');
    }
}
