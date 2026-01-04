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
     *
     * Agricultural Machinery Suppliers for Netherlands/EU Market
     */
    public function run(): void
    {
        $company = Company::first();
        $user = User::where('company_id', $company->id)->first();

        $suppliers = [
            // ========================================
            // DUTCH SUPPLIERS
            // ========================================
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00001',
                'name' => 'Dutch Steel Industries B.V.',
                'legal_name' => 'Dutch Steel Industries Besloten Vennootschap',
                'tax_id' => 'NL123456789B01',
                'email' => 'orders@dutchsteel.nl',
                'phone' => '+31-20-1234567',
                'website' => 'https://dutchsteel.nl',
                'address' => 'Industrieweg 45',
                'city' => 'Rotterdam',
                'state' => 'Zuid-Holland',
                'country' => 'Netherlands',
                'postal_code' => '3044 BC',
                'contact_person' => 'Jan van der Berg',
                'contact_email' => 'j.vanderberg@dutchsteel.nl',
                'contact_phone' => '+31-6-12345678',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 250000.00,
                'lead_time_days' => 5,
                'minimum_order_amount' => 1000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Primary steel supplier. Hardox certified. ISO 9001.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00002',
                'name' => 'Hydrauliek Centrum Nederland',
                'legal_name' => 'Hydrauliek Centrum Nederland B.V.',
                'tax_id' => 'NL987654321B01',
                'email' => 'verkoop@hydrauliekcentrum.nl',
                'phone' => '+31-40-2345678',
                'website' => 'https://hydrauliekcentrum.nl',
                'address' => 'Technoweg 12',
                'city' => 'Eindhoven',
                'state' => 'Noord-Brabant',
                'country' => 'Netherlands',
                'postal_code' => '5611 AB',
                'contact_person' => 'Pieter de Jong',
                'contact_email' => 'p.dejong@hydrauliekcentrum.nl',
                'contact_phone' => '+31-6-23456789',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 150000.00,
                'lead_time_days' => 3,
                'minimum_order_amount' => 500.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Hydraulic systems specialist. Same-day delivery available.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00003',
                'name' => 'Lely Parts & Service',
                'legal_name' => 'Lely Industries N.V.',
                'tax_id' => 'NL001234567B01',
                'email' => 'parts@lely.com',
                'phone' => '+31-33-4567890',
                'website' => 'https://lely.com',
                'address' => 'Cornelis van der Lelylaan 1',
                'city' => 'Maassluis',
                'state' => 'Zuid-Holland',
                'country' => 'Netherlands',
                'postal_code' => '3147 PB',
                'contact_person' => 'Annemieke Bakker',
                'contact_email' => 'a.bakker@lely.com',
                'contact_phone' => '+31-6-34567890',
                'currency' => 'EUR',
                'payment_terms_days' => 45,
                'credit_limit' => 500000.00,
                'lead_time_days' => 7,
                'minimum_order_amount' => 2500.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Dairy and livestock equipment. OEM parts.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00004',
                'name' => 'Priva B.V.',
                'legal_name' => 'Priva Holding B.V.',
                'tax_id' => 'NL002345678B01',
                'email' => 'sales@priva.nl',
                'phone' => '+31-174-522600',
                'website' => 'https://priva.nl',
                'address' => 'Zijlweg 3',
                'city' => 'De Lier',
                'state' => 'Zuid-Holland',
                'country' => 'Netherlands',
                'postal_code' => '2678 LC',
                'contact_person' => 'Saskia Vermeer',
                'contact_email' => 's.vermeer@priva.nl',
                'contact_phone' => '+31-6-45678901',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 350000.00,
                'lead_time_days' => 14,
                'minimum_order_amount' => 5000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Greenhouse automation. Climate control systems.',
            ],

            // ========================================
            // GERMAN SUPPLIERS
            // ========================================
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00005',
                'name' => 'Lemken GmbH & Co. KG',
                'legal_name' => 'Lemken GmbH & Co. KG',
                'tax_id' => 'DE123456789',
                'email' => 'parts@lemken.com',
                'phone' => '+49-2838-2040',
                'website' => 'https://lemken.com',
                'address' => 'Weseler Straße 5',
                'city' => 'Alpen',
                'state' => 'Nordrhein-Westfalen',
                'country' => 'Germany',
                'postal_code' => '46519',
                'contact_person' => 'Klaus Schmidt',
                'contact_email' => 'k.schmidt@lemken.com',
                'contact_phone' => '+49-171-1234567',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 400000.00,
                'lead_time_days' => 10,
                'minimum_order_amount' => 3000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Tillage equipment. Ploughs, cultivators, disc harrows.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00006',
                'name' => 'Amazone Werke',
                'legal_name' => 'Amazonen-Werke H. Dreyer SE & Co. KG',
                'tax_id' => 'DE234567890',
                'email' => 'export@amazone.de',
                'phone' => '+49-5405-5010',
                'website' => 'https://amazone.de',
                'address' => 'Am Amazonenwerk 9-13',
                'city' => 'Hasbergen',
                'state' => 'Niedersachsen',
                'country' => 'Germany',
                'postal_code' => '49205',
                'contact_person' => 'Heinrich Weber',
                'contact_email' => 'h.weber@amazone.de',
                'contact_phone' => '+49-172-2345678',
                'currency' => 'EUR',
                'payment_terms_days' => 45,
                'credit_limit' => 600000.00,
                'lead_time_days' => 14,
                'minimum_order_amount' => 5000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Sprayers, spreaders, seed drills. Premium quality.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00007',
                'name' => 'Grimme Landmaschinenfabrik',
                'legal_name' => 'Grimme Landmaschinenfabrik GmbH & Co. KG',
                'tax_id' => 'DE345678901',
                'email' => 'ersatzteile@grimme.de',
                'phone' => '+49-5561-880',
                'website' => 'https://grimme.com',
                'address' => 'Hunteburger Straße 32',
                'city' => 'Damme',
                'state' => 'Niedersachsen',
                'country' => 'Germany',
                'postal_code' => '49401',
                'contact_person' => 'Dieter Hoffmann',
                'contact_email' => 'd.hoffmann@grimme.de',
                'contact_phone' => '+49-173-3456789',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 450000.00,
                'lead_time_days' => 7,
                'minimum_order_amount' => 2000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Potato technology specialist. Harvesters, planters.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00008',
                'name' => 'Deutz-Fahr Parts',
                'legal_name' => 'SAME DEUTZ-FAHR Deutschland GmbH',
                'tax_id' => 'DE456789012',
                'email' => 'parts@deutz-fahr.de',
                'phone' => '+49-8331-8020',
                'website' => 'https://deutz-fahr.com',
                'address' => 'Deutz-Fahr-Straße 1',
                'city' => 'Lauingen',
                'state' => 'Bayern',
                'country' => 'Germany',
                'postal_code' => '89415',
                'contact_person' => 'Franz Müller',
                'contact_email' => 'f.mueller@deutz-fahr.de',
                'contact_phone' => '+49-174-4567890',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 300000.00,
                'lead_time_days' => 5,
                'minimum_order_amount' => 1500.00,
                'rating' => 4,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Tractor parts. Engines, transmissions, electronics.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00009',
                'name' => 'SKF Deutschland GmbH',
                'legal_name' => 'SKF GmbH',
                'tax_id' => 'DE567890123',
                'email' => 'bearings@skf.de',
                'phone' => '+49-9721-560',
                'website' => 'https://skf.com/de',
                'address' => 'Gunnar-Wester-Straße 12',
                'city' => 'Schweinfurt',
                'state' => 'Bayern',
                'country' => 'Germany',
                'postal_code' => '97421',
                'contact_person' => 'Wolfgang Braun',
                'contact_email' => 'w.braun@skf.com',
                'contact_phone' => '+49-175-5678901',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 200000.00,
                'lead_time_days' => 3,
                'minimum_order_amount' => 500.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Bearings, seals, lubrication systems.',
            ],

            // ========================================
            // OTHER EU SUPPLIERS
            // ========================================
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00010',
                'name' => 'Kverneland Group Italia',
                'legal_name' => 'Kverneland Group Italia S.p.A.',
                'tax_id' => 'IT12345678901',
                'email' => 'ricambi@kverneland.it',
                'phone' => '+39-0442-632111',
                'website' => 'https://kvernelandgroup.com',
                'address' => 'Via Masotto 122',
                'city' => 'Legnago',
                'state' => 'Veneto',
                'country' => 'Italy',
                'postal_code' => '37045',
                'contact_person' => 'Marco Rossi',
                'contact_email' => 'm.rossi@kverneland.it',
                'contact_phone' => '+39-335-1234567',
                'currency' => 'EUR',
                'payment_terms_days' => 45,
                'credit_limit' => 350000.00,
                'lead_time_days' => 12,
                'minimum_order_amount' => 2500.00,
                'rating' => 4,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Forage equipment, balers, mowers. Kubota group.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00011',
                'name' => 'Gates Europe',
                'legal_name' => 'Gates Europe BVBA',
                'tax_id' => 'BE0420449669',
                'email' => 'agri@gates.com',
                'phone' => '+32-2-5560211',
                'website' => 'https://gates.com/eu',
                'address' => 'Dr. Carlierlaan 30',
                'city' => 'Erembodegem',
                'state' => 'Oost-Vlaanderen',
                'country' => 'Belgium',
                'postal_code' => '9320',
                'contact_person' => 'Philippe Dubois',
                'contact_email' => 'p.dubois@gates.com',
                'contact_phone' => '+32-475-123456',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 100000.00,
                'lead_time_days' => 5,
                'minimum_order_amount' => 750.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Belts, hoses, hydraulic fittings. Fast delivery.',
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-00012',
                'name' => 'SSAB Europe Oy',
                'legal_name' => 'SSAB Europe Oy',
                'tax_id' => 'FI01234567',
                'email' => 'hardox@ssab.com',
                'phone' => '+358-20-5931000',
                'website' => 'https://ssab.com',
                'address' => 'Harvialantie 420',
                'city' => 'Hämeenlinna',
                'state' => 'Kanta-Häme',
                'country' => 'Finland',
                'postal_code' => '13300',
                'contact_person' => 'Mikko Virtanen',
                'contact_email' => 'm.virtanen@ssab.com',
                'contact_phone' => '+358-40-1234567',
                'currency' => 'EUR',
                'payment_terms_days' => 30,
                'credit_limit' => 300000.00,
                'lead_time_days' => 14,
                'minimum_order_amount' => 5000.00,
                'rating' => 5,
                'is_active' => true,
                'created_by' => $user->id,
                'notes' => 'Hardox, Strenx, Domex steel. Wear-resistant materials.',
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        // Attach products to suppliers based on their specialty
        $this->attachProductsToSuppliers($company);

        $this->command->info('Agricultural Machinery suppliers seeded: ' . count($suppliers) . ' suppliers');
    }

    /**
     * Attach products to suppliers based on category/specialty
     */
    private function attachProductsToSuppliers(Company $company): void
    {
        $suppliers = Supplier::where('company_id', $company->id)->get()->keyBy('supplier_code');

        // Get products by category slugs
        $steelProducts = Product::where('company_id', $company->id)
            ->whereHas('categories', fn($q) => $q->where('slug', 'steel-metals'))
            ->limit(10)->get();

        $hydraulicProducts = Product::where('company_id', $company->id)
            ->whereHas('categories', fn($q) => $q->where('slug', 'hydraulic-components'))
            ->limit(10)->get();

        $bearingProducts = Product::where('company_id', $company->id)
            ->whereHas('categories', fn($q) => $q->where('slug', 'bearings-seals'))
            ->limit(10)->get();

        // Dutch Steel Industries - Steel products
        if (isset($suppliers['SUP-00001']) && $steelProducts->isNotEmpty()) {
            foreach ($steelProducts as $index => $product) {
                $suppliers['SUP-00001']->products()->attach($product->id, [
                    'supplier_sku' => 'DSI-' . $product->sku,
                    'unit_price' => $product->cost_price * 0.95,
                    'currency' => 'EUR',
                    'minimum_order_qty' => 50,
                    'lead_time_days' => 5,
                    'is_preferred' => $index === 0,
                    'is_active' => true,
                ]);
            }
        }

        // Hydrauliek Centrum - Hydraulic components
        if (isset($suppliers['SUP-00002']) && $hydraulicProducts->isNotEmpty()) {
            foreach ($hydraulicProducts as $index => $product) {
                $suppliers['SUP-00002']->products()->attach($product->id, [
                    'supplier_sku' => 'HCN-' . $product->sku,
                    'unit_price' => $product->cost_price * 0.92,
                    'currency' => 'EUR',
                    'minimum_order_qty' => 10,
                    'lead_time_days' => 3,
                    'is_preferred' => true,
                    'is_active' => true,
                ]);
            }
        }

        // SKF - Bearings
        if (isset($suppliers['SUP-00009']) && $bearingProducts->isNotEmpty()) {
            foreach ($bearingProducts as $index => $product) {
                $suppliers['SUP-00009']->products()->attach($product->id, [
                    'supplier_sku' => 'SKF-' . $product->sku,
                    'unit_price' => $product->cost_price * 0.90,
                    'currency' => 'EUR',
                    'minimum_order_qty' => 25,
                    'lead_time_days' => 3,
                    'is_preferred' => true,
                    'is_active' => true,
                ]);
            }
        }
    }
}
