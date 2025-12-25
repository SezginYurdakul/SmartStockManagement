<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();
        $companyId = $company?->id;

        $warehouses = Warehouse::where('company_id', $companyId)->get();
        $products = Product::where('company_id', $companyId)->limit(200)->get(); // First 200 products

        if ($warehouses->isEmpty()) {
            $this->command->warn('No warehouses found! Please run WarehouseSeeder first.');
            return;
        }

        if ($products->isEmpty()) {
            $this->command->warn('No products found! Please run ProductSeeder first.');
            return;
        }

        $mainWarehouse = $warehouses->firstWhere('is_default', true) ?? $warehouses->first();
        $otherWarehouses = $warehouses->where('id', '!=', $mainWarehouse->id);

        $stockCount = 0;
        $movementCount = 0;

        foreach ($products as $product) {
            // Create stock in main warehouse for all products
            $mainQuantity = rand(50, 500);
            $unitCost = $product->cost_price ?? ($product->price * 0.7);
            $lotNumber = 'LOT-' . strtoupper(Str::random(8));

            $stock = Stock::create([
                'company_id' => $companyId,
                'warehouse_id' => $mainWarehouse->id,
                'product_id' => $product->id,
                'quantity_on_hand' => $mainQuantity,
                'quantity_reserved' => rand(0, min(10, $mainQuantity)),
                'unit_cost' => $unitCost,
                'lot_number' => $lotNumber,
                'expiry_date' => rand(0, 1) ? now()->addMonths(rand(3, 24)) : null,
                'received_date' => now()->subDays(rand(30, 90)),
                'status' => 'available',
                'notes' => 'Initial stock setup',
            ]);
            $stockCount++;

            // Create initial stock movement (receipt)
            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $mainWarehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'receipt',
                'transaction_type' => 'initial_stock',
                'quantity' => $mainQuantity,
                'quantity_before' => 0,
                'quantity_after' => $mainQuantity,
                'unit_cost' => $unitCost,
                'total_cost' => $mainQuantity * $unitCost,
                'lot_number' => $lotNumber,
                'reference_number' => 'INIT-' . str_pad($product->id, 6, '0', STR_PAD_LEFT),
                'notes' => 'Initial stock setup',
                'created_by' => 1, // Admin user
                'movement_date' => now()->subDays(rand(30, 90)),
            ]);
            $movementCount++;

            // Randomly add stock to other warehouses (30% chance per warehouse)
            foreach ($otherWarehouses as $warehouse) {
                if (rand(1, 100) <= 30) {
                    $quantity = rand(10, 100);
                    $otherLotNumber = 'LOT-' . strtoupper(Str::random(8));

                    $otherStock = Stock::create([
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'quantity_on_hand' => $quantity,
                        'quantity_reserved' => 0,
                        'unit_cost' => $unitCost,
                        'lot_number' => $otherLotNumber,
                        'expiry_date' => rand(0, 1) ? now()->addMonths(rand(3, 24)) : null,
                        'received_date' => now()->subDays(rand(1, 30)),
                        'status' => 'available',
                        'notes' => 'Stock transfer from main warehouse',
                    ]);
                    $stockCount++;

                    // Create transfer movement
                    StockMovement::create([
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'movement_type' => 'transfer',
                        'transaction_type' => 'transfer_order',
                        'quantity' => $quantity,
                        'quantity_before' => 0,
                        'quantity_after' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $quantity * $unitCost,
                        'from_warehouse_id' => $mainWarehouse->id,
                        'to_warehouse_id' => $warehouse->id,
                        'lot_number' => $otherLotNumber,
                        'reference_number' => 'TRF-' . str_pad(rand(1000, 9999), 6, '0', STR_PAD_LEFT),
                        'notes' => 'Stock transfer from main warehouse',
                        'created_by' => 1,
                        'movement_date' => now()->subDays(rand(1, 30)),
                    ]);
                    $movementCount++;
                }
            }

            // Add some random movements (sales/issues)
            if (rand(1, 100) <= 50) {
                $issueQty = rand(1, min(20, $mainQuantity));
                $newQty = $mainQuantity - $issueQty;

                StockMovement::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $mainWarehouse->id,
                    'product_id' => $product->id,
                    'movement_type' => 'issue',
                    'transaction_type' => 'sales_order',
                    'quantity' => $issueQty,
                    'quantity_before' => $mainQuantity,
                    'quantity_after' => $newQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $issueQty * $unitCost,
                    'lot_number' => $lotNumber,
                    'reference_number' => 'SO-' . str_pad(rand(1000, 9999), 6, '0', STR_PAD_LEFT),
                    'notes' => 'Sales order fulfillment',
                    'created_by' => 1,
                    'movement_date' => now()->subDays(rand(1, 14)),
                ]);
                $movementCount++;

                // Update stock quantity
                $stock->decrement('quantity_on_hand', $issueQty);
            }
        }

        $this->command->info("Stock seeded: {$stockCount} stock records");
        $this->command->info("Stock movements seeded: {$movementCount} movements");
    }
}
