<?php

namespace Database\Seeders;

use App\Enums\SalesOrderStatus;
use App\Enums\DeliveryNoteStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupPrice;
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
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->error('No user found. Please run UserSeeder first.');
            return;
        }

        $companyId = $user->company_id;

        $this->command->info('Creating Sales demo data...');

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

        $this->command->info('Sales demo data created successfully!');
    }

    private function createCustomerGroups(int $companyId): array
    {
        $groups = [
            [
                'company_id' => $companyId,
                'name' => 'VIP Customers',
                'code' => 'VIP',
                'description' => 'Top tier customers with best discounts',
                'discount_percentage' => 15,
                'payment_terms_days' => 60,
                'credit_limit' => 100000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Wholesale',
                'code' => 'WHOLESALE',
                'description' => 'Wholesale customers',
                'discount_percentage' => 10,
                'payment_terms_days' => 30,
                'credit_limit' => 50000,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Retail',
                'code' => 'RETAIL',
                'description' => 'Standard retail customers',
                'discount_percentage' => 5,
                'payment_terms_days' => 15,
                'credit_limit' => 10000,
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
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[0]->id, // VIP
                'customer_code' => 'CUS-00001',
                'name' => 'Acme Corporation',
                'email' => 'orders@acme.com',
                'phone' => '+1-555-0100',
                'tax_id' => 'TX123456789',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'contact_person' => 'John Smith',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[1]->id, // Wholesale
                'customer_code' => 'CUS-00002',
                'name' => 'Global Distributors Ltd',
                'email' => 'purchasing@globaldist.com',
                'phone' => '+1-555-0200',
                'tax_id' => 'TX987654321',
                'address' => '456 Commerce Ave',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal_code' => '90001',
                'country' => 'USA',
                'contact_person' => 'Jane Doe',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'customer_group_id' => $customerGroups[2]->id, // Retail
                'customer_code' => 'CUS-00003',
                'name' => 'Local Shop Inc',
                'email' => 'info@localshop.com',
                'phone' => '+1-555-0300',
                'address' => '100 Retail Lane',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'USA',
                'contact_person' => 'Bob Wilson',
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
        $products = Product::where('company_id', $companyId)->limit(5)->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found for group pricing.');
            return;
        }

        foreach ($products as $product) {
            $basePrice = $product->sale_price ?? $product->cost_price ?? 100;

            // VIP gets special price
            CustomerGroupPrice::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'customer_group_id' => $customerGroups[0]->id,
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                ],
                [
                    'price' => $basePrice * 0.80, // 20% off
                    'is_active' => true,
                ]
            );

            // Wholesale quantity discount
            CustomerGroupPrice::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'customer_group_id' => $customerGroups[1]->id,
                    'product_id' => $product->id,
                    'min_quantity' => 10,
                ],
                [
                    'price' => $basePrice * 0.85, // 15% off for 10+
                    'is_active' => true,
                ]
            );
        }
    }

    private function createSalesOrders(int $companyId, array $customers): array
    {
        $user = User::where('company_id', $companyId)->first();
        $products = Product::where('company_id', $companyId)->limit(3)->get();
        $warehouse = Warehouse::where('company_id', $companyId)->first();
        $defaultUom = UnitOfMeasure::first();

        if ($products->isEmpty()) {
            $this->command->warn('No products found for sales orders.');
            return [];
        }

        $orders = [];

        if (!$warehouse) {
            $this->command->warn('No warehouse found for sales orders.');
            return [];
        }

        // Order 1: Draft order
        $order1 = SalesOrder::firstOrCreate(
            ['company_id' => $companyId, 'order_number' => 'SO-2026-00001'],
            [
                'customer_id' => $customers[0]->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now(),
                'requested_delivery_date' => now()->addDays(7),
                'status' => SalesOrderStatus::DRAFT->value,
                'shipping_address' => $customers[0]->address,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'created_by' => $user->id,
            ]
        );

        if ($order1->wasRecentlyCreated && $products->isNotEmpty()) {
            $subtotal = 0;
            foreach ($products->take(2) as $product) {
                $qty = rand(1, 5);
                $price = $product->sale_price ?? $product->cost_price ?? 100;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                SalesOrderItem::create([
                    'sales_order_id' => $order1->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $qty,
                    'uom_id' => $product->unit_of_measure_id ?? $defaultUom->id,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ]);
            }
            $order1->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);
        }
        $orders[] = $order1;

        // Order 2: Confirmed order (ready for shipping)
        $order2 = SalesOrder::firstOrCreate(
            ['company_id' => $companyId, 'order_number' => 'SO-2026-00002'],
            [
                'customer_id' => $customers[1]->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->subDays(3),
                'requested_delivery_date' => now()->addDays(4),
                'status' => SalesOrderStatus::CONFIRMED->value,
                'shipping_address' => $customers[1]->address,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now()->subDays(2),
            ]
        );

        if ($order2->wasRecentlyCreated && $products->isNotEmpty()) {
            $subtotal = 0;
            foreach ($products as $product) {
                $qty = rand(5, 10);
                $price = $product->sale_price ?? $product->cost_price ?? 100;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                SalesOrderItem::create([
                    'sales_order_id' => $order2->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $qty,
                    'uom_id' => $product->unit_of_measure_id ?? $defaultUom->id,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ]);
            }
            $order2->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);
        }
        $orders[] = $order2;

        // Order 3: Delivered order (historical)
        $order3 = SalesOrder::firstOrCreate(
            ['company_id' => $companyId, 'order_number' => 'SO-2026-00003'],
            [
                'customer_id' => $customers[2]->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->subDays(14),
                'requested_delivery_date' => now()->subDays(7),
                'status' => SalesOrderStatus::DELIVERED->value,
                'shipping_address' => $customers[2]->address,
                'subtotal' => 500,
                'tax_amount' => 50,
                'discount_amount' => 25,
                'total_amount' => 525,
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now()->subDays(13),
            ]
        );

        if ($order3->wasRecentlyCreated && $products->first()) {
            $product = $products->first();
            $orderItem = SalesOrderItem::create([
                'sales_order_id' => $order3->id,
                'product_id' => $product->id,
                'quantity_ordered' => 5,
                'uom_id' => $product->unit_of_measure_id ?? $defaultUom->id,
                'unit_price' => 100,
                'line_total' => 500,
                'quantity_shipped' => 5,
            ]);

            // Create delivery note for this order
            $deliveryNote = DeliveryNote::create([
                'company_id' => $companyId,
                'sales_order_id' => $order3->id,
                'customer_id' => $customers[2]->id,
                'warehouse_id' => $warehouse->id,
                'delivery_number' => 'DN-2026-00001',
                'delivery_date' => now()->subDays(7),
                'status' => DeliveryNoteStatus::DELIVERED->value,
                'shipping_method' => 'Express Shipping',
                'tracking_number' => 'TRK123456789',
                'created_by' => $user->id,
                'delivered_at' => now()->subDays(7),
            ]);

            DeliveryNoteItem::create([
                'delivery_note_id' => $deliveryNote->id,
                'sales_order_item_id' => $orderItem->id,
                'product_id' => $product->id,
                'quantity_shipped' => 5,
            ]);
        }
        $orders[] = $order3;

        return $orders;
    }
}
