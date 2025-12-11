<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            Permission::create(['name' => 'products.view', 'display_name' => 'View Products', 'module' => 'products']),
            Permission::create(['name' => 'products.create', 'display_name' => 'Create Products', 'module' => 'products']),
            Permission::create(['name' => 'products.update', 'display_name' => 'Update Products', 'module' => 'products']),
            Permission::create(['name' => 'products.delete', 'display_name' => 'Delete Products', 'module' => 'products']),
        ];

        // Create role with all permissions
        $this->role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);
        $this->role->permissions()->attach($permissions);

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);
    }

    /** @test */
    public function it_can_list_products()
    {
        // Create test products
        Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2',
            'sku' => 'TEST-002',
            'price' => 149.99,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'sku',
                        'price',
                        'stock',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'current_page',
                'total',
            ])
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_products_by_category()
    {
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
            'category_id' => $category->id,
        ]);

        Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2',
            'sku' => 'TEST-002',
            'price' => 149.99,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/products?category_id=' . $category->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_id', $category->id);
    }

    /** @test */
    public function it_can_sort_products_by_price()
    {
        Product::create([
            'name' => 'Expensive Product',
            'slug' => 'expensive',
            'sku' => 'TEST-001',
            'price' => 999.99,
            'stock' => 10,
        ]);

        Product::create([
            'name' => 'Cheap Product',
            'slug' => 'cheap',
            'sku' => 'TEST-002',
            'price' => 9.99,
            'stock' => 5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/products?sort_by=price&sort_order=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Cheap Product')
            ->assertJsonPath('data.1.name', 'Expensive Product');
    }

    /** @test */
    public function it_can_create_a_product()
    {
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $productData = [
            'name' => 'New Product',
            'sku' => 'TEST-001',
            'description' => 'Test description',
            'price' => 99.99,
            'stock' => 10,
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'price',
                    'stock',
                ]
            ])
            ->assertJsonPath('data.name', 'New Product')
            ->assertJsonPath('data.slug', 'new-product');

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'sku' => 'TEST-001',
            'slug' => 'new-product',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_product()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock']);
    }

    /** @test */
    public function it_validates_unique_sku_when_creating_product()
    {
        Product::create([
            'name' => 'Existing Product',
            'slug' => 'existing',
            'sku' => 'DUPLICATE-SKU',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'New Product',
                'sku' => 'DUPLICATE-SKU',
                'price' => 149.99,
                'stock' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /** @test */
    public function it_can_show_a_single_product()
    {
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'slug',
                'sku',
                'price',
                'stock',
                'category',
                'images',
                'variants',
            ])
            ->assertJsonPath('name', 'Test Product')
            ->assertJsonPath('category.id', $category->id);
    }

    /** @test */
    public function it_returns_404_for_non_existent_product()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/products/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_update_a_product()
    {
        $product = Product::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $updateData = [
            'name' => 'New Name',
            'price' => 149.99,
            'stock' => 20,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/products/' . $product->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.price', '149.99')
            ->assertJsonPath('data.stock', 20);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New Name',
            'price' => 149.99,
            'stock' => 20,
        ]);
    }

    /** @test */
    public function it_can_delete_a_product()
    {
        $product = Product::create([
            'name' => 'Product to Delete',
            'slug' => 'product-to-delete',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Product deleted successfully');

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_access_products()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_permission_to_create_products()
    {
        // Create user without permissions
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'New Product',
                'sku' => 'TEST-001',
                'price' => 99.99,
                'stock' => 10,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_requires_permission_to_update_products()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create user without permissions
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission, 'sanctum')
            ->putJson('/api/products/' . $product->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_requires_permission_to_delete_products()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Create user without permissions
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission, 'sanctum')
            ->deleteJson('/api/products/' . $product->id);

        $response->assertStatus(403);
    }
}
