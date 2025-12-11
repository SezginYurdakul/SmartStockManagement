<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Exception;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = new ProductService();
    }

    /** @test */
    public function it_can_create_a_product_with_auto_generated_slug()
    {
        $data = [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ];

        $product = $this->productService->create($data);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('test-product', $product->slug);
        $this->assertEquals('TEST-001', $product->sku);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(10, $product->stock);
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'slug' => 'test-product',
        ]);
    }

    /** @test */
    public function it_can_create_a_product_with_provided_slug()
    {
        $data = [
            'name' => 'Test Product',
            'slug' => 'custom-slug',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ];

        $product = $this->productService->create($data);

        $this->assertEquals('custom-slug', $product->slug);
        $this->assertDatabaseHas('products', [
            'slug' => 'custom-slug',
        ]);
    }

    /** @test */
    public function it_generates_unique_slug_when_duplicate_exists()
    {
        // Create first product with slug
        Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create second product with same name
        $data = [
            'name' => 'Test Product',
            'sku' => 'TEST-002',
            'price' => 89.99,
            'stock' => 5,
        ];

        $product = $this->productService->create($data);

        $this->assertEquals('test-product-1', $product->slug);
        $this->assertDatabaseHas('products', [
            'slug' => 'test-product-1',
        ]);
    }

    /** @test */
    public function it_can_update_a_product()
    {
        $product = Product::create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'price' => 149.99,
            'stock' => 15,
        ];

        $updatedProduct = $this->productService->update($product, $updateData);

        $this->assertEquals('Updated Name', $updatedProduct->name);
        $this->assertEquals(149.99, $updatedProduct->price);
        $this->assertEquals(15, $updatedProduct->stock);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'price' => 149.99,
        ]);
    }

    /** @test */
    public function it_can_delete_a_product_with_related_data()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Add related images
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'test/image.jpg',
            'disk' => 'public',
            'order' => 1,
        ]);

        // Add related variants
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Size: Large',
            'sku' => 'TEST-001-L',
            'price' => 109.99,
            'stock' => 5,
            'attributes' => ['size' => 'L'],
        ]);

        $result = $this->productService->delete($product);

        $this->assertTrue($result);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        // Product images are hard deleted when product is deleted
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        $this->assertSoftDeleted('product_variants', ['product_id' => $product->id]);
    }

    /** @test */
    public function it_applies_filters_correctly()
    {
        // Create test products with different attributes
        Product::create([
            'name' => 'Active Product',
            'slug' => 'active-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
            'is_active' => true,
            'is_featured' => false,
        ]);

        Product::create([
            'name' => 'Featured Product',
            'slug' => 'featured-product',
            'sku' => 'TEST-002',
            'price' => 149.99,
            'stock' => 5,
            'is_active' => true,
            'is_featured' => true,
        ]);

        Product::create([
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'sku' => 'TEST-003',
            'price' => 79.99,
            'stock' => 0,
            'is_active' => false,
        ]);

        // Test is_active filter
        $query = Product::query();
        $filtered = $this->productService->applyFilters($query, ['is_active' => true]);
        $this->assertEquals(2, $filtered->count());

        // Test is_featured filter
        $query = Product::query();
        $filtered = $this->productService->applyFilters($query, ['is_featured' => true]);
        $this->assertEquals(1, $filtered->count());

        // Test stock_status filter
        $query = Product::query();
        $filtered = $this->productService->applyFilters($query, ['stock_status' => 'out_of_stock']);
        $this->assertEquals(1, $filtered->count());
    }

    /** @test */
    public function it_applies_sorting_correctly()
    {
        Product::create(['name' => 'Product A', 'slug' => 'a', 'sku' => '001', 'price' => 50, 'stock' => 10]);
        Product::create(['name' => 'Product B', 'slug' => 'b', 'sku' => '002', 'price' => 100, 'stock' => 5]);
        Product::create(['name' => 'Product C', 'slug' => 'c', 'sku' => '003', 'price' => 75, 'stock' => 15]);

        // Sort by price ascending
        $query = Product::query();
        $sorted = $this->productService->applySorting($query, 'price', 'asc')->get();
        $this->assertEquals(50, $sorted->first()->price);
        $this->assertEquals(100, $sorted->last()->price);

        // Sort by stock descending
        $query = Product::query();
        $sorted = $this->productService->applySorting($query, 'stock', 'desc')->get();
        $this->assertEquals(15, $sorted->first()->stock);
        $this->assertEquals(5, $sorted->last()->stock);
    }

    /** @test */
    public function it_generates_unique_slug_for_product_name()
    {
        $slug = $this->productService->generateUniqueSlug('Test Product');
        $this->assertEquals('test-product', $slug);

        // Create product with that slug
        Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Generate unique slug for duplicate name
        $slug = $this->productService->generateUniqueSlug('Test Product');
        $this->assertEquals('test-product-1', $slug);

        // Create another with same name
        Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-1',
            'sku' => 'TEST-002',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $slug = $this->productService->generateUniqueSlug('Test Product');
        $this->assertEquals('test-product-2', $slug);
    }

    /** @test */
    public function it_rolls_back_transaction_on_create_failure()
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        // Invalid data that will cause database constraint violation
        $data = [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ];

        // Create a product with same SKU to cause unique constraint violation
        Product::create([
            'name' => 'Existing Product',
            'slug' => 'existing-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        try {
            $this->productService->create($data);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to create product', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_delete_failure()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        // Mock DB to simulate failure
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        // Mock the delete to throw exception
        $product = $this->mock(Product::class);
        $product->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $product->shouldReceive('getAttribute')->with('name')->andReturn('Test Product');
        $product->shouldReceive('images')->andThrow(new Exception('Database error'));

        $this->expectException(Exception::class);

        try {
            $this->productService->delete($product);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to delete product', $e->getMessage());
            throw $e;
        }
    }
}
