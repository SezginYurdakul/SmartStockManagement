<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create permissions
        $permissions = [
            Permission::create(['name' => 'products.view', 'display_name' => 'View Products', 'module' => 'products']),
            Permission::create(['name' => 'products.update', 'display_name' => 'Update Products', 'module' => 'products']),
        ];

        // Create role with permissions
        $this->role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);
        $this->role->permissions()->attach($permissions);

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);

        // Create test product
        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);
    }

    /** @test */
    public function it_can_upload_a_single_image()
    {
        $file = UploadedFile::fake()->image('product.jpg', 1000, 1000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'path',
                        'order',
                        'is_primary',
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('product_images', [
            'product_id' => $this->product->id,
        ]);
    }

    /** @test */
    public function it_can_upload_multiple_images()
    {
        $files = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
            UploadedFile::fake()->image('product3.jpg'),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => $files,
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(3, 'data');

        $this->assertEquals(3, $this->product->images()->count());
    }

    /** @test */
    public function it_can_upload_image_with_alt_text()
    {
        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => [$file],
                'alt_text' => ['Product Image Description'],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $this->product->id,
            'alt_text' => 'Product Image Description',
        ]);
    }

    /** @test */
    public function it_can_set_primary_image_on_upload()
    {
        $files = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => $files,
                'is_primary' => [false, true], // Second image is primary
            ]);

        $response->assertStatus(201);

        $images = $this->product->images()->get();
        $this->assertFalse($images[0]->is_primary);
        $this->assertTrue($images[1]->is_primary);
    }

    /** @test */
    public function it_validates_image_file_type()
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => [$file],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
    public function it_validates_image_file_size()
    {
        $file = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB, max is 5MB

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => [$file],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
    public function it_validates_maximum_images_per_upload()
    {
        $files = [];
        for ($i = 0; $i < 11; $i++) { // Max is 10
            $files[] = UploadedFile::fake()->image("product{$i}.jpg");
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => $files,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    /** @test */
    public function it_can_update_image_order()
    {
        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test.jpg',
            'order' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/products/{$this->product->id}/images/{$image->id}", [
                'order' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order', 5);

        $this->assertDatabaseHas('product_images', [
            'id' => $image->id,
            'order' => 5,
        ]);
    }

    /** @test */
    public function it_can_update_image_to_primary()
    {
        $image1 = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test1.jpg',
            'is_primary' => true,
        ]);

        $image2 = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test2.jpg',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/products/{$this->product->id}/images/{$image2->id}", [
                'is_primary' => true,
            ]);

        $response->assertStatus(200);

        // First image should no longer be primary
        $this->assertDatabaseHas('product_images', [
            'id' => $image1->id,
            'is_primary' => false,
        ]);

        // Second image should now be primary
        $this->assertDatabaseHas('product_images', [
            'id' => $image2->id,
            'is_primary' => true,
        ]);
    }

    /** @test */
    public function it_can_update_image_alt_text()
    {
        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test.jpg',
            'alt_text' => 'Old description',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/products/{$this->product->id}/images/{$image->id}", [
                'alt_text' => 'New description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.alt_text', 'New description');

        $this->assertDatabaseHas('product_images', [
            'id' => $image->id,
            'alt_text' => 'New description',
        ]);
    }

    /** @test */
    public function it_prevents_updating_image_from_different_product()
    {
        $otherProduct = Product::create([
            'name' => 'Other Product',
            'slug' => 'other-product',
            'sku' => 'OTHER-001',
            'price' => 149.99,
            'stock' => 5,
        ]);

        $image = ProductImage::create([
            'product_id' => $otherProduct->id,
            'path' => 'products/test.jpg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/products/{$this->product->id}/images/{$image->id}", [
                'alt_text' => 'New description',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Image not found for this product');
    }

    /** @test */
    public function it_can_delete_an_image()
    {
        Storage::fake('public');

        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test.jpg',
        ]);

        // Create fake file
        Storage::disk('public')->put('products/test.jpg', 'fake content');

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/products/{$this->product->id}/images/{$image->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Image deleted successfully');

        $this->assertDatabaseMissing('product_images', [
            'id' => $image->id,
        ]);
    }

    /** @test */
    public function it_prevents_deleting_image_from_different_product()
    {
        $otherProduct = Product::create([
            'name' => 'Other Product',
            'slug' => 'other-product',
            'sku' => 'OTHER-001',
            'price' => 149.99,
            'stock' => 5,
        ]);

        $image = ProductImage::create([
            'product_id' => $otherProduct->id,
            'path' => 'products/test.jpg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/products/{$this->product->id}/images/{$image->id}");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Image not found for this product');

        $this->assertDatabaseHas('product_images', [
            'id' => $image->id,
        ]);
    }

    /** @test */
    public function it_can_reorder_images()
    {
        $image1 = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test1.jpg',
            'order' => 1,
        ]);

        $image2 = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test2.jpg',
            'order' => 2,
        ]);

        $image3 = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test3.jpg',
            'order' => 3,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images/reorder", [
                'images' => [
                    ['id' => $image3->id, 'order' => 1],
                    ['id' => $image1->id, 'order' => 2],
                    ['id' => $image2->id, 'order' => 3],
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Images reordered successfully');

        $this->assertDatabaseHas('product_images', ['id' => $image3->id, 'order' => 1]);
        $this->assertDatabaseHas('product_images', ['id' => $image1->id, 'order' => 2]);
        $this->assertDatabaseHas('product_images', ['id' => $image2->id, 'order' => 3]);
    }

    /** @test */
    public function it_requires_authentication_to_upload_images()
    {
        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->postJson("/api/products/{$this->product->id}/images", [
            'images' => [$file],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_permission_to_upload_images()
    {
        $userWithoutPermission = User::factory()->create();

        $file = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($userWithoutPermission, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/images", [
                'images' => [$file],
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_requires_permission_to_delete_images()
    {
        $userWithoutPermission = User::factory()->create();

        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'path' => 'products/test.jpg',
        ]);

        $response = $this->actingAs($userWithoutPermission, 'sanctum')
            ->deleteJson("/api/products/{$this->product->id}/images/{$image->id}");

        $response->assertStatus(403);
    }
}
