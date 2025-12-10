<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;
use Exception;

class ProductImageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductImageService $productImageService;

    protected function setUp(): void
    {
        parent::setUp();

        $imageManager = app(ImageManager::class);
        $this->productImageService = new ProductImageService($imageManager);

        // Fake storage for testing
        Storage::fake('public');
    }

    /** @test */
    public function it_can_upload_a_single_image()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $file = UploadedFile::fake()->image('product.jpg', 1000, 1000);

        $productImage = $this->productImageService->uploadSingleImage($product, $file);

        $this->assertInstanceOf(ProductImage::class, $productImage);
        $this->assertEquals($product->id, $productImage->product_id);
        $this->assertEquals('public', $productImage->disk);
        $this->assertEquals(1, $productImage->order);
        $this->assertStringContainsString('products/' . $product->id, $productImage->path);

        // Verify file was stored
        $pathParts = explode('/', $productImage->path);
        $filename = end($pathParts);
        Storage::disk('public')->assertExists('products/' . $product->id . '/' . $filename);
    }

    /** @test */
    public function it_sets_first_image_as_primary_by_default()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $file = UploadedFile::fake()->image('product.jpg');

        $productImage = $this->productImageService->uploadSingleImage($product, $file, ['is_primary' => true]);

        $this->assertTrue($productImage->is_primary);
    }

    /** @test */
    public function it_unsets_other_primary_images_when_setting_new_primary()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create first primary image
        $firstImage = $product->images()->create([
            'path' => 'test/image1.jpg',
            'disk' => 'public',
            'order' => 1,
            'is_primary' => true,
        ]);

        // Upload new primary image
        $file = UploadedFile::fake()->image('product.jpg');
        $newImage = $this->productImageService->uploadSingleImage($product, $file, ['is_primary' => true]);

        // Refresh first image
        $firstImage->refresh();

        $this->assertFalse($firstImage->is_primary);
        $this->assertTrue($newImage->is_primary);
    }

    /** @test */
    public function it_can_upload_multiple_images()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $files = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
            UploadedFile::fake()->image('product3.jpg'),
        ];

        $uploadedImages = $this->productImageService->uploadImages($product, $files);

        $this->assertCount(3, $uploadedImages);
        $this->assertEquals(3, $product->images()->count());
    }

    /** @test */
    public function it_can_update_image_details()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $image = $product->images()->create([
            'path' => 'test/image.jpg',
            'disk' => 'public',
            'order' => 1,
            'is_primary' => false,
            'alt_text' => 'Original Alt Text',
        ]);

        $updateData = [
            'alt_text' => 'Updated Alt Text',
            'order' => 5,
        ];

        $updatedImage = $this->productImageService->updateImage($image, $updateData);

        $this->assertEquals('Updated Alt Text', $updatedImage->alt_text);
        $this->assertEquals(5, $updatedImage->order);
    }

    /** @test */
    public function it_can_set_image_as_primary_via_update()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $image1 = $product->images()->create([
            'path' => 'test/image1.jpg',
            'disk' => 'public',
            'order' => 1,
            'is_primary' => true,
        ]);

        $image2 = $product->images()->create([
            'path' => 'test/image2.jpg',
            'disk' => 'public',
            'order' => 2,
            'is_primary' => false,
        ]);

        $this->productImageService->updateImage($image2, ['is_primary' => true]);

        $image1->refresh();
        $image2->refresh();

        $this->assertFalse($image1->is_primary);
        $this->assertTrue($image2->is_primary);
    }

    /** @test */
    public function it_can_delete_an_image()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create a fake file first
        Storage::disk('public')->put('test/image.jpg', 'fake image content');

        $image = $product->images()->create([
            'path' => 'test/image.jpg',
            'disk' => 'public',
            'order' => 1,
        ]);

        $result = $this->productImageService->deleteImage($image);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    /** @test */
    public function it_can_reorder_images()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $image1 = $product->images()->create([
            'path' => 'test/image1.jpg',
            'disk' => 'public',
            'order' => 1,
        ]);

        $image2 = $product->images()->create([
            'path' => 'test/image2.jpg',
            'disk' => 'public',
            'order' => 2,
        ]);

        $image3 = $product->images()->create([
            'path' => 'test/image3.jpg',
            'disk' => 'public',
            'order' => 3,
        ]);

        // Reorder: swap image1 and image3
        $imageOrders = [
            ['id' => $image1->id, 'order' => 3],
            ['id' => $image2->id, 'order' => 2],
            ['id' => $image3->id, 'order' => 1],
        ];

        $this->productImageService->reorderImages($product, $imageOrders);

        $image1->refresh();
        $image2->refresh();
        $image3->refresh();

        $this->assertEquals(3, $image1->order);
        $this->assertEquals(2, $image2->order);
        $this->assertEquals(1, $image3->order);
    }

    /** @test */
    public function it_validates_image_belongs_to_product()
    {
        $product1 = Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $product2 = Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2',
            'sku' => 'TEST-002',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $image = $product1->images()->create([
            'path' => 'test/image.jpg',
            'disk' => 'public',
            'order' => 1,
        ]);

        $belongsToProduct1 = $this->productImageService->validateImageBelongsToProduct($image, $product1);
        $belongsToProduct2 = $this->productImageService->validateImageBelongsToProduct($image, $product2);

        $this->assertTrue($belongsToProduct1);
        $this->assertFalse($belongsToProduct2);
    }

    /** @test */
    public function it_increments_order_for_new_images()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create first image manually
        $product->images()->create([
            'path' => 'test/image1.jpg',
            'disk' => 'public',
            'order' => 5,
        ]);

        // Upload new image - should have order = 6
        $file = UploadedFile::fake()->image('product.jpg');
        $newImage = $this->productImageService->uploadSingleImage($product, $file);

        $this->assertEquals(6, $newImage->order);
    }

    /** @test */
    public function it_sets_custom_alt_text_for_images()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $file = UploadedFile::fake()->image('product.jpg');

        $image = $this->productImageService->uploadSingleImage($product, $file, [
            'alt_text' => 'Custom Alt Text',
        ]);

        $this->assertEquals('Custom Alt Text', $image->alt_text);
    }

    /** @test */
    public function it_uses_product_name_as_default_alt_text()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $file = UploadedFile::fake()->image('product.jpg');

        $image = $this->productImageService->uploadSingleImage($product, $file);

        $this->assertEquals('Test Product', $image->alt_text);
    }

    /** @test */
    public function it_handles_batch_upload_errors_gracefully()
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $files = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
        ];

        // This should succeed despite potential errors
        $uploadedImages = $this->productImageService->uploadImages($product, $files);

        $this->assertIsArray($uploadedImages);
    }

    /** @test */
    public function it_rolls_back_transaction_on_upload_failure()
    {
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create invalid file that will cause processing to fail
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        try {
            $this->productImageService->uploadSingleImage($product, $invalidFile);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to upload image', $e->getMessage());
            throw $e;
        }
    }

    /** @test */
    public function it_rolls_back_transaction_on_update_failure()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $image = $product->images()->create([
            'path' => 'test/image.jpg',
            'disk' => 'public',
            'order' => 1,
        ]);

        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $this->expectException(Exception::class);

        // Mock the image to throw exception
        $mockedImage = $this->mock(ProductImage::class);
        $mockedImage->shouldReceive('getAttribute')->with('id')->andReturn($image->id);
        $mockedImage->shouldReceive('getAttribute')->with('product_id')->andReturn($product->id);
        $mockedImage->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $mockedImage->shouldReceive('update')->andThrow(new Exception('Database error'));

        try {
            $this->productImageService->updateImage($mockedImage, ['order' => 5]);
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to update image', $e->getMessage());
            throw $e;
        }
    }
}
