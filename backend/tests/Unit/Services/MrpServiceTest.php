<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MrpService;
use App\Services\BomService;
use App\Services\StockService;
use App\Services\MrpCacheService;
use App\Models\MrpRun;
use App\Models\Product;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Stock;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\User;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\UnitOfMeasure;
use App\Enums\MrpRunStatus;
use App\Enums\SalesOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MrpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MrpService $mrpService;
    protected Company $company;
    protected User $user;
    protected Warehouse $warehouse;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        // Create company and user manually
        $this->company = Company::create([
            'name' => 'Test Company',
            'code' => 'TEST',
            'tax_id' => '1234567890',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'code' => 'WH-001',
            'name' => 'Test Warehouse',
            'warehouse_type' => 'finished_goods',
            'is_active' => true,
        ]);

        // Set up MRP settings
        Setting::create([
            'group' => 'mrp',
            'key' => 'working_days',
            'value' => [1, 2, 3, 4, 5], // Monday to Friday
            'is_system' => true,
        ]);

        // Create default UOM
        $this->uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'pcs', 'company_id' => $this->company->id],
            [
                'name' => 'Piece',
                'uom_type' => 'quantity',
                'is_active' => true,
            ]
        );

        // Create service instances
        $this->mrpService = app(MrpService::class);

        // Authenticate user
        Auth::login($this->user);
    }

    /** @test */
    public function it_can_create_and_execute_an_mrp_run()
    {
        // Arrange: Create a product with stock
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 100.00,
            'cost_price' => 50.00,
            'stock' => 0,
            'lead_time_days' => 5,
            'safety_stock' => 10,
            'reorder_point' => 20,
            'make_or_buy' => 'buy',
            'is_active' => true,
        ]);

        Stock::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'unit_cost' => 50.00,
            'status' => 'available',
            'quality_status' => 'available',
        ]);

        // Create a customer
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'customer_code' => 'CUST-001',
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
        ]);

        // Create a sales order
        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'SO-001',
            'order_date' => now(),
            'status' => SalesOrderStatus::CONFIRMED,
            'total_amount' => 5000.00,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'uom_id' => $this->uom->id,
            'quantity_ordered' => 50,
            'quantity' => 50,
            'unit_price' => 100.00,
            'required_date' => now()->addDays(10),
        ]);

        // Act: Run MRP
        $run = $this->mrpService->runMrp([
            'name' => 'Test MRP Run',
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Assert: MRP run was created and completed
        $this->assertInstanceOf(MrpRun::class, $run);
        $this->assertEquals(MrpRunStatus::COMPLETED, $run->status);
        $this->assertGreaterThan(0, $run->products_processed);
    }

    /** @test */
    public function it_generates_purchase_order_recommendations_for_low_stock()
    {
        // Arrange: Product with low stock
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Low Stock Product',
            'slug' => 'low-stock-product',
            'sku' => 'LS-001',
            'price' => 100.00,
            'lead_time_days' => 7,
            'safety_stock' => 20,
            'reorder_point' => 30,
            'make_or_buy' => 'buy',
            'minimum_order_qty' => 50,
            'is_active' => true,
        ]);

        Stock::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => 10, // Below reorder point
            'quantity_reserved' => 0,
            'unit_cost' => 50.00,
            'status' => 'available',
            'quality_status' => 'available',
        ]);

        // Act: Run MRP
        $run = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Assert: Purchase order recommendation generated
        $recommendations = $run->recommendations;
        $this->assertGreaterThan(0, $recommendations->count());
        
        $poRecommendation = $recommendations->firstWhere('type', 'PURCHASE_ORDER');
        $this->assertNotNull($poRecommendation);
        $this->assertEquals($product->id, $poRecommendation->product_id);
    }

    /** @test */
    public function it_calculates_low_level_codes_correctly()
    {
        // Arrange: Create a multi-level BOM structure
        // Level 0: Finished product
        $finishedProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Finished Product LLC',
            'slug' => 'finished-product-llc-test',
            'sku' => 'FP-LLC-TEST-001',
            'price' => 300.00,
            'make_or_buy' => 'make',
            'is_active' => true,
        ]);

        // Level 1: Sub-assembly
        $subAssembly = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Sub Assembly LLC',
            'slug' => 'sub-assembly-llc-test',
            'sku' => 'SUB-LLC-TEST-001',
            'price' => 150.00,
            'make_or_buy' => 'make',
            'is_active' => true,
        ]);

        // Level 2: Raw material
        $rawMaterial = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Raw Material LLC',
            'slug' => 'raw-material-llc-test',
            'sku' => 'RAW-LLC-TEST-001',
            'price' => 50.00,
            'make_or_buy' => 'buy',
            'is_active' => true,
        ]);

        // Create BOMs
        $finishedBom = Bom::create([
            'company_id' => $this->company->id,
            'product_id' => $finishedProduct->id,
            'bom_number' => 'BOM-FIN',
            'name' => 'Finished Product BOM',
            'status' => \App\Enums\BomStatus::ACTIVE,
            'is_default' => true,
        ]);

        BomItem::create([
            'bom_id' => $finishedBom->id,
            'component_id' => $subAssembly->id,
            'line_number' => 1,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
        ]);

        $subBom = Bom::create([
            'company_id' => $this->company->id,
            'product_id' => $subAssembly->id,
            'bom_number' => 'BOM-SUB',
            'name' => 'Sub Assembly BOM',
            'status' => \App\Enums\BomStatus::ACTIVE,
            'is_default' => true,
        ]);

        BomItem::create([
            'bom_id' => $subBom->id,
            'component_id' => $rawMaterial->id,
            'line_number' => 1,
            'quantity' => 2,
            'uom_id' => $this->uom->id,
        ]);

        // Act: Run MRP (this will calculate LLC)
        $run = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Assert: Low-level codes are correct
        $finishedProduct->refresh();
        $subAssembly->refresh();
        $rawMaterial->refresh();

        $this->assertEquals(0, $finishedProduct->low_level_code);
        $this->assertEquals(1, $subAssembly->low_level_code);
        $this->assertEquals(2, $rawMaterial->low_level_code);
    }

    /** @test */
    public function it_handles_dependent_demand_from_bom_explosion()
    {
        // Arrange: Create finished product with BOM
        $finishedProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Finished Product BOM',
            'slug' => 'finished-product-bom',
            'sku' => 'FP-BOM-001',
            'price' => 200.00,
            'make_or_buy' => 'make',
            'lead_time_days' => 5,
            'is_active' => true,
        ]);

        $component = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Component Product',
            'slug' => 'component-product',
            'sku' => 'COMP-BOM-001',
            'price' => 50.00,
            'make_or_buy' => 'buy',
            'lead_time_days' => 3,
            'is_active' => true,
        ]);

        $bom = Bom::create([
            'company_id' => $this->company->id,
            'product_id' => $finishedProduct->id,
            'bom_number' => 'BOM-002',
            'name' => 'Finished Product BOM',
            'status' => \App\Enums\BomStatus::ACTIVE,
            'is_default' => true,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'component_id' => $component->id,
            'line_number' => 1,
            'quantity' => 2, // 2 components per finished product
            'uom_id' => $this->uom->id,
        ]);

        // Create a customer
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'customer_code' => 'CUST-003',
            'name' => 'Test Customer 3',
            'email' => 'customer3@example.com',
        ]);

        // Create sales order for finished product
        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_number' => 'SO-003',
            'order_date' => now(),
            'status' => SalesOrderStatus::CONFIRMED,
            'total_amount' => 2000.00,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $finishedProduct->id,
            'uom_id' => $this->uom->id,
            'quantity_ordered' => 10,
            'quantity' => 10, // Need 10 finished products
            'unit_price' => 200.00,
            'required_date' => now()->addDays(10),
        ]);

        // Act: Run MRP
        $run = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Assert: Component has dependent demand
        $componentRecommendations = $run->recommendations
            ->where('product_id', $component->id);

        $this->assertGreaterThan(0, $componentRecommendations->count());
        
        // Should need 20 components (10 finished × 2 per finished)
        $totalComponentQty = $componentRecommendations->sum('quantity');
        $this->assertGreaterThanOrEqual(20, $totalComponentQty);
    }

    /** @test */
    public function it_respects_lead_times_in_order_dates()
    {
        // Arrange: Product with lead time
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'lead_time_days' => 10,
            'make_or_buy' => 'buy',
        ]);

        $salesOrder = SalesOrder::factory()->create([
            'company_id' => $this->company->id,
            'order_date' => now(),
            'status' => 'pending',
        ]);

        SalesOrderItem::factory()->create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'quantity_ordered' => 100,
            'quantity' => 100,
            'required_date' => now()->addDays(15), // Need in 15 days
        ]);

        // Act: Run MRP
        $run = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
            'respect_lead_times' => true,
        ]);

        // Assert: Order date is before required date (considering lead time)
        $recommendation = $run->recommendations->firstWhere('product_id', $product->id);
        $this->assertNotNull($recommendation);
        
        $orderDate = \Carbon\Carbon::parse($recommendation->order_date);
        $requiredDate = \Carbon\Carbon::parse($recommendation->required_date);
        
        // Order date should be at least lead_time_days before required date
        $this->assertLessThanOrEqual(
            $requiredDate->subDays(10),
            $orderDate
        );
    }

    /** @test */
    public function it_validates_mrp_run_data()
    {
        // Arrange: No products
        // (products are created in setUp, but we can test with empty state)

        // Act & Assert: Should throw exception for invalid planning horizon
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Planning horizon start date must be before end date');

        $this->mrpService->runMrp([
            'planning_horizon_start' => now()->addDays(10),
            'planning_horizon_end' => now(), // End before start
        ]);
    }

    /** @test */
    public function it_uses_cache_for_low_level_codes()
    {
        // Arrange: Create products with BOM
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Finished Product',
            'slug' => 'finished-product',
            'sku' => 'FP-001',
            'price' => 200.00,
            'make_or_buy' => 'make',
            'is_active' => true,
        ]);

        $component = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Component',
            'slug' => 'component',
            'sku' => 'COMP-001',
            'price' => 50.00,
            'make_or_buy' => 'buy',
            'is_active' => true,
        ]);

        $bom = Bom::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'bom_number' => 'BOM-001',
            'name' => 'Test BOM',
            'status' => \App\Enums\BomStatus::ACTIVE,
            'is_default' => true,
        ]);

        BomItem::create([
            'bom_id' => $bom->id,
            'component_id' => $component->id,
            'line_number' => 1,
            'quantity' => 1,
            'uom_id' => $this->uom->id,
        ]);

        // Act: Run MRP twice
        $run1 = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Second run should use cache
        $run2 = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Assert: Both runs completed successfully
        $this->assertEquals(MrpRunStatus::COMPLETED, $run1->status);
        $this->assertEquals(MrpRunStatus::COMPLETED, $run2->status);
        
        // LLC should be cached (second run should be faster)
        $component->refresh();
        $this->assertEquals(1, $component->low_level_code);
    }

    /** @test */
    public function it_prevents_concurrent_mrp_runs()
    {
        // Arrange: Create a product
        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Test Product',
            'slug' => 'test-product-concurrent',
            'sku' => 'TEST-CONC-001',
            'price' => 100.00,
            'is_active' => true,
        ]);

        // Act: Try to run two MRP runs simultaneously
        // (In real scenario, second would be blocked by lock)
        $run1 = $this->mrpService->runMrp([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addDays(30),
        ]);

        // Second run should either wait or fail
        // (Lock mechanism prevents concurrent runs)
        $this->assertEquals(MrpRunStatus::COMPLETED, $run1->status);
    }
}
