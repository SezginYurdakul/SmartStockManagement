<?php

namespace Database\Seeders;

use App\Enums\SalesOrderStatus;
use App\Enums\DeliveryNoteStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupPrice;
use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Agricultural Machinery Sales Data for Netherlands/EU Market
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->error('No user found. Please run UserSeeder first.');
            return;
        }

        $companyId = $user->company_id;
        $company = Company::find($companyId);

        if (!$company) {
            $this->command->error('Company not found for user.');
            return;
        }

        $this->command->info('Creating Agricultural Machinery Sales demo data...');

        DB::transaction(function () use ($companyId) {
            // Create customer groups
            $customerGroups = $this->createCustomerGroups($companyId);
            $this->command->info('Created ' . count($customerGroups) . ' customer groups');

            // Create customers
            $customers = $this->createCustomers($companyId, $customerGroups);
            $this->command->info('Created ' . count($customers) . ' customers');

            // Create group prices for some products
            $this->createGroupPrices($companyId, $customerGroups);
            $this->command->info('Created group prices');

            // Create sample sales orders
            $salesOrders = $this->createSalesOrders($companyId, $customers);
            $this->command->info('Created ' . count($salesOrders) . ' sales orders');
        });

        $this->command->info("Agricultural Machinery Sales data created successfully for {$company->name}!");
    }

    private function createCustomerGroups(int $companyId): array
    {
        $groups = [
            [
                'company_id' => $companyId,
                'name' => 'Authorized Dealers',
                'code' => 'DEALER',
                'description' => 'Authorized agricultural machinery dealers with best pricing',
                'discount_percentage' => 25,
                'payment_terms_days' => 60,
                'credit_limit' => 500000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Agricultural Cooperatives',
                'code' => 'COOP',
                'description' => 'Farmer cooperatives and agricultural associations',
                'discount_percentage' => 20,
                'payment_terms_days' => 45,
                'credit_limit' => 250000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Large Farms',
                'code' => 'FARM-L',
                'description' => 'Large-scale agricultural enterprises',
                'discount_percentage' => 15,
                'payment_terms_days' => 30,
                'credit_limit' => 150000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Small Farms',
                'code' => 'FARM-S',
                'description' => 'Small to medium family farms',
                'discount_percentage' => 10,
                'payment_terms_days' => 30,
                'credit_limit' => 50000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Contractors',
                'code' => 'CONTR',
                'description' => 'Agricultural service contractors (loonwerkers)',
                'discount_percentage' => 18,
                'payment_terms_days' => 45,
                'credit_limit' => 300000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Export Customers',
                'code' => 'EXPORT',
                'description' => 'International export customers',
                'discount_percentage' => 12,
                'payment_terms_days' => 90,
                'credit_limit' => 750000,
                'is_active' => true,
            ],
        ];

        $result = [];
        foreach ($groups as $group) {
            $result[] = CustomerGroup::firstOrCreate(
                ['company_id' => $group['company_id'], 'code' => $group['code']],
                $group
            );
        }

        return $result;
    }

    private function createCustomers(int $companyId, array $customerGroups): array
    {
        $customers = [
            // ========================================
            // AUTHORIZED DEALERS (Netherlands)
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[0]->id, // Dealer
                'customer_code' => 'CUS-00001',
                'name' => 'AgriDealers Groningen B.V.',
                'email' => 'inkoop@agridealers-groningen.nl',
                'phone' => '+31-50-1234567',
                'tax_id' => 'NL123456789B01',
                'address' => 'Landbouwweg 45',
                'city' => 'Groningen',
                'state' => 'Groningen',
                'postal_code' => '9727 KK',
                'country' => 'Netherlands',
                'contact_person' => 'Jan Huisman',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[0]->id, // Dealer
                'customer_code' => 'CUS-00002',
                'name' => 'Tractoren Centrum Brabant',
                'email' => 'verkoop@tcbrabant.nl',
                'phone' => '+31-13-2345678',
                'tax_id' => 'NL234567890B01',
                'address' => 'Industrieweg 89',
                'city' => 'Tilburg',
                'state' => 'Noord-Brabant',
                'postal_code' => '5038 XM',
                'country' => 'Netherlands',
                'contact_person' => 'Piet van den Berg',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[0]->id, // Dealer
                'customer_code' => 'CUS-00003',
                'name' => 'Friesland Agri Machines',
                'email' => 'orders@frieslandagri.nl',
                'phone' => '+31-58-3456789',
                'tax_id' => 'NL345678901B01',
                'address' => 'Zuiderweg 123',
                'city' => 'Leeuwarden',
                'state' => 'Friesland',
                'postal_code' => '8911 AD',
                'country' => 'Netherlands',
                'contact_person' => 'Sjoerd Hoekstra',
                'is_active' => true,
            ],

            // ========================================
            // AGRICULTURAL COOPERATIVES
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[1]->id, // Coop
                'customer_code' => 'CUS-00004',
                'name' => 'Coöperatie Flevoland Agrarisch',
                'email' => 'machines@coopflevoland.nl',
                'phone' => '+31-320-456789',
                'tax_id' => 'NL456789012B01',
                'address' => 'Polderweg 567',
                'city' => 'Lelystad',
                'state' => 'Flevoland',
                'postal_code' => '8219 PL',
                'country' => 'Netherlands',
                'contact_person' => 'Hendrik Visser',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[1]->id, // Coop
                'customer_code' => 'CUS-00005',
                'name' => 'ZLTO Werktuigencoöperatie',
                'email' => 'inkoop@zlto-machines.nl',
                'phone' => '+31-73-5678901',
                'tax_id' => 'NL567890123B01',
                'address' => 'Brabantlaan 234',
                'city' => 'Den Bosch',
                'state' => 'Noord-Brabant',
                'postal_code' => '5216 TV',
                'country' => 'Netherlands',
                'contact_person' => 'Maria Jansen',
                'is_active' => true,
            ],

            // ========================================
            // LARGE FARMS
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[2]->id, // Large Farm
                'customer_code' => 'CUS-00006',
                'name' => 'Akkerbouwbedrijf De Groot',
                'email' => 'bedrijf@degroot-akkerbouw.nl',
                'phone' => '+31-527-678901',
                'tax_id' => 'NL678901234B01',
                'address' => 'Polderkade 78',
                'city' => 'Emmeloord',
                'state' => 'Flevoland',
                'postal_code' => '8302 AD',
                'country' => 'Netherlands',
                'contact_person' => 'Willem de Groot',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[2]->id, // Large Farm
                'customer_code' => 'CUS-00007',
                'name' => 'Melkveebedrijf Hollands Glorie',
                'email' => 'info@hollandsglorie.nl',
                'phone' => '+31-348-789012',
                'tax_id' => 'NL789012345B01',
                'address' => 'Weidezicht 156',
                'city' => 'Woerden',
                'state' => 'Utrecht',
                'postal_code' => '3441 HL',
                'country' => 'Netherlands',
                'contact_person' => 'Kees Bakker',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[2]->id, // Large Farm
                'customer_code' => 'CUS-00008',
                'name' => 'Glastuinbouw Westland BV',
                'email' => 'techniek@glaswestland.nl',
                'phone' => '+31-174-890123',
                'tax_id' => 'NL890123456B01',
                'address' => 'Kassenweg 234',
                'city' => 'Naaldwijk',
                'state' => 'Zuid-Holland',
                'postal_code' => '2671 BK',
                'country' => 'Netherlands',
                'contact_person' => 'Arjan van der Linden',
                'is_active' => true,
            ],

            // ========================================
            // SMALL FARMS
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[3]->id, // Small Farm
                'customer_code' => 'CUS-00009',
                'name' => 'Boerderij De Zonnehoeve',
                'email' => 'info@dezonnehoeve.nl',
                'phone' => '+31-575-901234',
                'address' => 'Achterweg 12',
                'city' => 'Zutphen',
                'state' => 'Gelderland',
                'postal_code' => '7203 AP',
                'country' => 'Netherlands',
                'contact_person' => 'Familie Mulder',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[3]->id, // Small Farm
                'customer_code' => 'CUS-00010',
                'name' => 'Fruitbedrijf Appelhof',
                'email' => 'contact@appelhof.nl',
                'phone' => '+31-345-012345',
                'address' => 'Boomgaardlaan 89',
                'city' => 'Geldermalsen',
                'state' => 'Gelderland',
                'postal_code' => '4191 LE',
                'country' => 'Netherlands',
                'contact_person' => 'Johan Peters',
                'is_active' => true,
            ],

            // ========================================
            // CONTRACTORS (Loonwerkers)
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[4]->id, // Contractors
                'customer_code' => 'CUS-00011',
                'name' => 'Loonbedrijf Van der Ploeg',
                'email' => 'planning@vanderploeg-loon.nl',
                'phone' => '+31-594-123456',
                'tax_id' => 'NL901234567B01',
                'address' => 'Machineweg 45',
                'city' => 'Veendam',
                'state' => 'Groningen',
                'postal_code' => '9641 JK',
                'country' => 'Netherlands',
                'contact_person' => 'Gerrit van der Ploeg',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[4]->id, // Contractors
                'customer_code' => 'CUS-00012',
                'name' => 'Agrarisch Loonwerk Zeeland',
                'email' => 'info@loonwerk-zeeland.nl',
                'phone' => '+31-118-234567',
                'tax_id' => 'NL012345678B01',
                'address' => 'Polderstraat 167',
                'city' => 'Goes',
                'state' => 'Zeeland',
                'postal_code' => '4461 HM',
                'country' => 'Netherlands',
                'contact_person' => 'Pieter Leenhouts',
                'is_active' => true,
            ],

            // ========================================
            // EXPORT CUSTOMERS (Belgium, Germany)
            // ========================================
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[5]->id, // Export
                'customer_code' => 'CUS-00013',
                'name' => 'Agri Machines Vlaanderen BVBA',
                'email' => 'aankoop@agri-vlaanderen.be',
                'phone' => '+32-3-4567890',
                'tax_id' => 'BE0123456789',
                'address' => 'Landbouwstraat 234',
                'city' => 'Antwerpen',
                'state' => 'Antwerpen',
                'postal_code' => '2000',
                'country' => 'Belgium',
                'contact_person' => 'Luc Peeters',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[5]->id, // Export
                'customer_code' => 'CUS-00014',
                'name' => 'Landmaschinen Nordrhein GmbH',
                'email' => 'einkauf@landmaschinen-nr.de',
                'phone' => '+49-2151-567890',
                'tax_id' => 'DE123456789',
                'address' => 'Agrarstraße 78',
                'city' => 'Krefeld',
                'state' => 'Nordrhein-Westfalen',
                'postal_code' => '47803',
                'country' => 'Germany',
                'contact_person' => 'Hans Schmidt',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[5]->id, // Export
                'customer_code' => 'CUS-00015',
                'name' => 'Wallonie Agri SPRL',
                'email' => 'commandes@wallonie-agri.be',
                'phone' => '+32-81-678901',
                'tax_id' => 'BE9876543210',
                'address' => 'Route de la Ferme 45',
                'city' => 'Namur',
                'state' => 'Wallonie',
                'postal_code' => '5000',
                'country' => 'Belgium',
                'contact_person' => 'Jean-Marc Dubois',
                'is_active' => true,
            ],
        ];

        $result = [];
        foreach ($customers as $customer) {
            $result[] = Customer::firstOrCreate(
                ['company_id' => $customer['company_id'], 'customer_code' => $customer['customer_code']],
                $customer
            );
        }

        return $result;
    }

    private function createGroupPrices(int $companyId, array $customerGroups): void
    {
        // Get machinery products (tractors, implements)
        $products = Product::where('company_id', $companyId)
            ->whereHas('categories', function ($q) {
                $q->whereIn('slug', [
                    'compact-tractors', 'utility-tractors', 'ploughs', 'disc-harrows'
                ]);
            })
            ->limit(5)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No machinery products found for group pricing.');
            return;
        }

        // Get default currency for company
        $defaultCurrency = Currency::where('code', 'EUR')->first()
            ?? Currency::where('is_active', true)->first();

        foreach ($products as $product) {
            $basePrice = $product->price ?? $product->cost_price ?? 50000;

            // Dealers get best price
            CustomerGroupPrice::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'customer_group_id' => $customerGroups[0]->id, // Dealer
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                ],
                [
                    'price' => $basePrice * 0.75, // 25% off
                    'currency_id' => $defaultCurrency?->id,
                    'is_active' => true,
                ]
            );

            // Cooperatives get good pricing
            CustomerGroupPrice::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'customer_group_id' => $customerGroups[1]->id, // Coop
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                ],
                [
                    'price' => $basePrice * 0.80, // 20% off
                    'currency_id' => $defaultCurrency?->id,
                    'is_active' => true,
                ]
            );

            // Contractors volume discount
            CustomerGroupPrice::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'customer_group_id' => $customerGroups[4]->id, // Contractors
                    'product_id' => $product->id,
                    'min_quantity' => 3,
                ],
                [
                    'price' => $basePrice * 0.82, // 18% off for 3+
                    'currency_id' => $defaultCurrency?->id,
                    'is_active' => true,
                ]
            );
        }
    }

    private function createSalesOrders(int $companyId, array $customers): array
    {
        $user = User::where('company_id', $companyId)->first();
        $products = Product::where('company_id', $companyId)
            ->whereHas('categories', function ($q) {
                $q->whereNotIn('slug', ['steel-metals', 'fasteners', 'bearings-seals', 'rubber-plastics']);
            })
            ->limit(15)->get();
        $warehouse = Warehouse::where('company_id', $companyId)->where('code', 'WH-MAIN')->first()
            ?? Warehouse::where('company_id', $companyId)->first();
        $defaultUom = UnitOfMeasure::first();

        if ($products->isEmpty()) {
            $this->command->warn('No machinery products found for sales orders.');
            return [];
        }

        $orders = [];
        $deliveryNoteCount = 0;

        if (!$warehouse) {
            $this->command->warn('No warehouse found for sales orders.');
            return [];
        }

        // Helper function to create order items
        $createOrderItems = function ($order, $productList, $minQty = 1, $maxQty = 3) use ($defaultUom) {
            $subtotal = 0;
            $items = [];
            foreach ($productList as $product) {
                $qty = rand($minQty, $maxQty);
                $price = $product->price ?? $product->cost_price ?? 25000;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                $items[] = SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $qty,
                    'uom_id' => $product->unit_of_measure_id ?? $defaultUom->id,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ]);
            }
            $taxAmount = $subtotal * 0.21; // Dutch BTW 21%
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount
            ]);
            return $items;
        };

        $orderNumber = 1;

        // === DRAFT ORDERS (3) - New quotations ===
        foreach (array_slice($customers, 0, 3) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(0, 2)),
                    'requested_delivery_date' => now()->addDays(rand(14, 30)),
                    'status' => SalesOrderStatus::DRAFT->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'notes' => 'Spring season machinery inquiry',
                    'created_by' => $user->id,
                ]
            );

            if ($order->wasRecentlyCreated) {
                $createOrderItems($order, $products->random(rand(1, 2)), 1, 2);
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === PENDING APPROVAL ORDERS (2) - Large orders awaiting approval ===
        foreach (array_slice($customers, 3, 2) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(1, 3)),
                    'requested_delivery_date' => now()->addDays(rand(21, 45)),
                    'status' => SalesOrderStatus::PENDING_APPROVAL->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'notes' => 'Large fleet order - requires management approval',
                    'created_by' => $user->id,
                ]
            );

            if ($order->wasRecentlyCreated) {
                $createOrderItems($order, $products->random(rand(2, 4)), 2, 5);
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === APPROVED ORDERS (2) - Ready for production/shipping ===
        foreach (array_slice($customers, 5, 2) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(5, 7)),
                    'requested_delivery_date' => now()->addDays(rand(7, 14)),
                    'status' => SalesOrderStatus::APPROVED->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now()->subDays(rand(2, 3)),
                ]
            );

            if ($order->wasRecentlyCreated) {
                $createOrderItems($order, $products->random(rand(1, 3)), 1, 2);
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === CONFIRMED ORDERS (3) - Ready for delivery ===
        foreach (array_slice($customers, 7, 3) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(10, 14)),
                    'requested_delivery_date' => now()->addDays(rand(1, 7)),
                    'status' => SalesOrderStatus::CONFIRMED->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now()->subDays(rand(7, 10)),
                ]
            );

            if ($order->wasRecentlyCreated) {
                $createOrderItems($order, $products->random(rand(1, 2)), 1, 3);
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === PARTIALLY SHIPPED ORDERS (2) - With delivery notes ===
        foreach (array_slice($customers, 10, 2) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(21, 28)),
                    'requested_delivery_date' => now()->addDays(rand(1, 7)),
                    'status' => SalesOrderStatus::PARTIALLY_SHIPPED->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'notes' => 'Partial delivery - remaining items in production',
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now()->subDays(rand(18, 21)),
                ]
            );

            if ($order->wasRecentlyCreated) {
                $orderItems = $createOrderItems($order, $products->random(3), 2, 4);

                // Create partial delivery note
                $deliveryNoteCount++;
                $deliveryNote = DeliveryNote::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'delivery_number' => sprintf('DN-2026-%05d', $deliveryNoteCount),
                    'delivery_date' => now()->subDays(rand(5, 10)),
                    'status' => DeliveryNoteStatus::SHIPPED->value,
                    'shipping_method' => 'Truck Delivery',
                    'tracking_number' => 'NL-TRK-' . str_pad($deliveryNoteCount, 6, '0', STR_PAD_LEFT),
                    'created_by' => $user->id,
                    'delivered_at' => now()->subDays(rand(5, 10)),
                    'notes' => 'First shipment - tractors',
                ]);

                // Ship first item fully
                if (isset($orderItems[0])) {
                    $shippedQty = $orderItems[0]->quantity_ordered;
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'sales_order_item_id' => $orderItems[0]->id,
                        'product_id' => $orderItems[0]->product_id,
                        'quantity_shipped' => $shippedQty,
                    ]);
                    $orderItems[0]->update(['quantity_shipped' => $shippedQty]);
                }
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === SHIPPED ORDERS (3) - In transit ===
        foreach (array_slice($customers, 12, 3) as $customer) {
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2026-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays(rand(28, 35)),
                    'requested_delivery_date' => now()->subDays(rand(1, 5)),
                    'status' => SalesOrderStatus::SHIPPED->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now()->subDays(rand(25, 30)),
                ]
            );

            if ($order->wasRecentlyCreated) {
                $orderItems = $createOrderItems($order, $products->random(rand(1, 2)), 1, 2);

                // Full shipment
                $deliveryNoteCount++;
                $deliveryNote = DeliveryNote::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'delivery_number' => sprintf('DN-2026-%05d', $deliveryNoteCount),
                    'delivery_date' => now()->subDays(rand(2, 5)),
                    'status' => DeliveryNoteStatus::SHIPPED->value,
                    'shipping_method' => $customer->country === 'Netherlands' ? 'Truck Delivery' : 'International Freight',
                    'tracking_number' => 'NL-TRK-' . str_pad($deliveryNoteCount, 6, '0', STR_PAD_LEFT),
                    'created_by' => $user->id,
                    'delivered_at' => now()->subDays(rand(2, 5)),
                ]);

                foreach ($orderItems as $orderItem) {
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'sales_order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'quantity_shipped' => $orderItem->quantity_ordered,
                    ]);
                    $orderItem->update(['quantity_shipped' => $orderItem->quantity_ordered]);
                }
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === DELIVERED ORDERS (5) - Completed ===
        foreach (array_slice($customers, 0, 5) as $customer) {
            $daysAgo = rand(45, 90);
            $order = SalesOrder::firstOrCreate(
                ['company_id' => $companyId, 'order_number' => sprintf('SO-2025-%05d', $orderNumber)],
                [
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'order_date' => now()->subDays($daysAgo),
                    'requested_delivery_date' => now()->subDays($daysAgo - 21),
                    'status' => SalesOrderStatus::DELIVERED->value,
                    'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now()->subDays($daysAgo - 2),
                ]
            );

            if ($order->wasRecentlyCreated) {
                $orderItems = $createOrderItems($order, $products->random(rand(1, 3)), 1, 2);

                // Completed delivery
                $deliveryNoteCount++;
                $deliveryNote = DeliveryNote::create([
                    'company_id' => $companyId,
                    'sales_order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'warehouse_id' => $warehouse->id,
                    'delivery_number' => sprintf('DN-2025-%05d', $deliveryNoteCount),
                    'delivery_date' => now()->subDays($daysAgo - 14),
                    'status' => DeliveryNoteStatus::DELIVERED->value,
                    'shipping_method' => 'Truck Delivery',
                    'tracking_number' => 'NL-TRK-' . str_pad($deliveryNoteCount, 6, '0', STR_PAD_LEFT),
                    'created_by' => $user->id,
                    'delivered_by' => $user->id,
                    'delivered_at' => now()->subDays($daysAgo - 16),
                    'notes' => 'Delivered and installed at customer location',
                ]);

                foreach ($orderItems as $orderItem) {
                    DeliveryNoteItem::create([
                        'delivery_note_id' => $deliveryNote->id,
                        'sales_order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'quantity_shipped' => $orderItem->quantity_ordered,
                    ]);
                    $orderItem->update(['quantity_shipped' => $orderItem->quantity_ordered]);
                }
            }
            $orders[] = $order;
            $orderNumber++;
        }

        // === CANCELLED ORDER (1) ===
        $customer = $customers[5];
        $order = SalesOrder::firstOrCreate(
            ['company_id' => $companyId, 'order_number' => sprintf('SO-2025-%05d', $orderNumber)],
            [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->subDays(rand(60, 90)),
                'requested_delivery_date' => now()->subDays(rand(40, 50)),
                'status' => SalesOrderStatus::CANCELLED->value,
                'shipping_address' => $customer->address . ', ' . $customer->postal_code . ' ' . $customer->city,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'notes' => 'Cancelled - Customer changed requirements',
                'created_by' => $user->id,
            ]
        );

        if ($order->wasRecentlyCreated) {
            $createOrderItems($order, $products->random(2), 1, 2);
        }
        $orders[] = $order;

        $this->command->info("Created {$deliveryNoteCount} delivery notes");

        return $orders;
    }
}
