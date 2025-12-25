# Centralized Exception Handling in Laravel APIs: A Practical Guide

## Introduction

Exception handling is one of the most overlooked aspects of API development. Many developers scatter try-catch blocks throughout their codebase, leading to inconsistent error responses, duplicated code, and maintenance nightmares. This article explores the benefits of centralized exception handling and demonstrates how to implement it effectively in Laravel applications.

## The Problem with Scattered Exception Handling

Consider this common anti-pattern:

```php
// CategoryController.php - Bad Practice
public function destroy(Category $category)
{
    try {
        if ($category->products()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete category with products'
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Deleted']);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

// ProductController.php - Different format!
public function destroy(Product $product)
{
    try {
        $product->delete();
        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json([
            'msg' => 'Failed to delete',
            'details' => $e->getMessage()
        ], 500);
    }
}
```

**Problems with this approach:**

1. **Inconsistent response formats** - Each controller uses different field names (`error`, `msg`, `details`)
2. **Duplicated logic** - Try-catch blocks repeated everywhere
3. **Mixed responsibilities** - Controllers handle both business logic and error formatting
4. **Hard to maintain** - Changing error format requires modifying every controller
5. **Difficult to test** - Must test error formatting in every controller

## The Solution: Centralized Exception Handling

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        HTTP Request                              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Controller                                │
│  • Validates input                                               │
│  • Delegates to Service                                          │
│  • Returns success response                                      │
│  • Does NOT catch exceptions                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Service Layer                               │
│  • Contains business logic                                       │
│  • Throws BusinessException for rule violations                  │
│  • Throws specific exceptions for specific errors                │
└─────────────────────────────────────────────────────────────────┘
                              │
                    Exception thrown?
                     /            \
                   No              Yes
                   /                \
                  ▼                  ▼
┌──────────────────────┐   ┌─────────────────────────────────────┐
│   Success Response   │   │    Global Exception Handler         │
│   (from Controller)  │   │    (bootstrap/app.php)              │
└──────────────────────┘   │  • Catches all exceptions           │
                           │  • Maps to appropriate HTTP status   │
                           │  • Returns consistent JSON format    │
                           └─────────────────────────────────────┘
```

### Implementation

#### Step 1: Create a Custom Business Exception

```php
<?php
// app/Exceptions/BusinessException.php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message, int $statusCode = 422)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
```

**Why a custom exception?**

- Separates business errors from system errors
- Allows attaching HTTP status codes to exceptions
- Makes intent clear in service layer
- Enables specific handling in exception handler

#### Step 2: Configure the Global Exception Handler

```php
<?php
// bootstrap/app.php (Laravel 11)

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions): void {

        // Model not found (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();
                $message = $previous instanceof ModelNotFoundException
                    ? class_basename($previous->getModel()) . ' not found'
                    : 'Resource not found';

                return response()->json([
                    'message' => $message,
                    'error' => 'not_found'
                ], 404);
            }
        });

        // Unauthenticated (401)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'unauthenticated'
                ], 401);
            }
        });

        // Forbidden (403)
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied',
                    'error' => 'forbidden'
                ], 403);
            }
        });

        // Validation error (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'error' => 'validation_error',
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // Business logic errors
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'business_error'
                ], $e->getStatusCode());
            }
        });
    })
    ->create();
```

#### Step 3: Clean Service Layer

```php
<?php
// app/Services/CategoryService.php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class CategoryService
{
    public function delete(Category $category): bool
    {
        Log::info('Deleting category', [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        // Business rule validations - throw exceptions, don't return errors
        if ($category->products()->count() > 0) {
            throw new BusinessException(
                'Cannot delete category with products. Please move or delete products first.',
                409
            );
        }

        if ($category->children()->count() > 0) {
            throw new BusinessException(
                'Cannot delete category with subcategories. Please delete subcategories first.',
                409
            );
        }

        $result = $category->delete();

        Log::info('Category deleted successfully', ['category_id' => $category->id]);

        return $result;
    }

    public function update(Category $category, array $data): Category
    {
        // Prevent category from being its own parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            throw new BusinessException('Category cannot be its own parent', 400);
        }

        // Prevent circular reference
        if (isset($data['parent_id']) && $this->wouldCreateCircularReference($category, $data['parent_id'])) {
            throw new BusinessException('Cannot set parent: would create circular reference', 400);
        }

        $category->update($data);

        return $category->fresh();
    }
}
```

#### Step 4: Slim Controllers

```php
<?php
// app/Http/Controllers/CategoryController.php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function destroy(Category $category)
    {
        // No try-catch! Let exceptions propagate to global handler
        $this->categoryService->delete($category);

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        // Service may throw BusinessException - that's fine!
        $category = $this->categoryService->update($category, $validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }
}
```

## Benefits of Centralized Exception Handling

### 1. Consistent API Responses

Every error response follows the same format:

```json
{
    "message": "Human-readable error message",
    "error": "error_type"
}
```

Frontend developers can rely on this structure for all error cases.

### 2. Single Point of Change

Need to add a request ID to all error responses? Change one file:

```php
$exceptions->render(function (BusinessException $e, Request $request) {
    return response()->json([
        'message' => $e->getMessage(),
        'error' => 'business_error',
        'request_id' => request()->header('X-Request-ID') // Added everywhere!
    ], $e->getStatusCode());
});
```

### 3. Separation of Concerns

| Layer | Responsibility |
|-------|---------------|
| Controller | Input validation, delegation, success responses |
| Service | Business logic, throwing appropriate exceptions |
| Exception Handler | Error formatting, HTTP status mapping |

### 4. Cleaner Code

**Before (scattered handling):**
```php
public function destroy(Category $category)
{
    try {
        if ($category->products()->count() > 0) {
            return response()->json(['error' => 'Has products'], 400);
        }
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'Has children'], 400);
        }
        $category->delete();
        return response()->json(['message' => 'Deleted']);
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return response()->json(['error' => 'Server error'], 500);
    }
}
```

**After (centralized handling):**
```php
public function destroy(Category $category)
{
    $this->categoryService->delete($category);
    return response()->json(['message' => 'Category deleted successfully']);
}
```

### 5. Improved Testability

This is perhaps the most significant benefit. Centralized exception handling dramatically improves testability in several ways:

#### A. Service Layer Tests Are Pure

```php
// tests/Unit/Services/CategoryServiceTest.php

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryService();
    }

    /** @test */
    public function it_throws_exception_when_deleting_category_with_products()
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Cannot delete category with products');

        $this->service->delete($category);
    }

    /** @test */
    public function it_throws_exception_with_correct_status_code()
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        try {
            $this->service->delete($category);
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(409, $e->getStatusCode());
            $this->assertStringContainsString('products', $e->getMessage());
        }
    }

    /** @test */
    public function it_prevents_circular_reference()
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('circular reference');

        $this->service->update($parent, ['parent_id' => $child->id]);
    }

    /** @test */
    public function it_deletes_category_successfully()
    {
        $category = Category::factory()->create();

        $result = $this->service->delete($category);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
```

**Benefits:**
- Tests focus on business logic only
- No need to parse JSON responses
- Can test exception types and status codes directly
- Clear, readable assertions

#### B. Controller Tests Focus on HTTP Layer

```php
// tests/Feature/CategoryControllerTest.php

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_409_when_deleting_category_with_products()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'business_error',
                'message' => 'Cannot delete category with products. Please move or delete products first.'
            ]);
    }

    /** @test */
    public function it_returns_400_for_circular_reference()
    {
        $user = User::factory()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->actingAs($user)
            ->putJson("/api/categories/{$parent->id}", [
                'parent_id' => $child->id
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'business_error'
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson('/api/categories/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'not_found',
                'message' => 'Category not found'
            ]);
    }

    /** @test */
    public function it_deletes_category_successfully()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully'
            ]);
    }
}
```

**Benefits:**
- Tests verify HTTP response format
- Tests verify correct status codes
- Integration tests cover full request lifecycle
- Easy to test all error scenarios

#### C. Exception Handler Tests

```php
// tests/Feature/ExceptionHandlerTest.php

class ExceptionHandlerTest extends TestCase
{
    /** @test */
    public function it_formats_business_exception_correctly()
    {
        Route::get('/test-business-exception', function () {
            throw new BusinessException('Test error message', 409);
        });

        $response = $this->getJson('/test-business-exception');

        $response->assertStatus(409)
            ->assertExactJson([
                'message' => 'Test error message',
                'error' => 'business_error'
            ]);
    }

    /** @test */
    public function it_formats_model_not_found_correctly()
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'not_found'
            ]);
    }

    /** @test */
    public function it_formats_validation_exception_correctly()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/products', [
                // missing required fields
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'error',
                'errors'
            ]);
    }

    /** @test */
    public function it_formats_authentication_exception_correctly()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
                'error' => 'unauthenticated'
            ]);
    }
}
```

#### D. Testing Different Scenarios Systematically

```php
// tests/Feature/ErrorResponseConsistencyTest.php

class ErrorResponseConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * @dataProvider errorScenariosProvider
     */
    public function all_error_responses_have_consistent_format(
        string $method,
        string $url,
        array $data,
        int $expectedStatus,
        string $expectedErrorType
    ) {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->json($method, $url, $data);

        $response->assertStatus($expectedStatus)
            ->assertJsonStructure([
                'message',
                'error'
            ])
            ->assertJson([
                'error' => $expectedErrorType
            ]);
    }

    public static function errorScenariosProvider(): array
    {
        return [
            'not found' => ['GET', '/api/products/99999', [], 404, 'not_found'],
            'validation error' => ['POST', '/api/products', [], 422, 'validation_error'],
            // Add more scenarios as needed
        ];
    }
}
```

### 6. Easier Debugging

With centralized handling, you can add debugging in one place:

```php
$exceptions->render(function (BusinessException $e, Request $request) {
    // Log all business exceptions
    Log::warning('Business exception occurred', [
        'message' => $e->getMessage(),
        'status' => $e->getStatusCode(),
        'url' => $request->fullUrl(),
        'user_id' => Auth::id(),
        'input' => $request->except(['password']),
    ]);

    return response()->json([
        'message' => $e->getMessage(),
        'error' => 'business_error'
    ], $e->getStatusCode());
});
```

### 7. Security Benefits

Centralized handling prevents accidental information leakage:

```php
// In production, don't expose internal errors
$exceptions->render(function (\Throwable $e, Request $request) {
    if ($request->is('api/*') && !($e instanceof BusinessException)) {
        Log::error('Unhandled exception', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => app()->isProduction()
                ? 'An unexpected error occurred'
                : $e->getMessage(),
            'error' => 'server_error'
        ], 500);
    }
});
```

## HTTP Status Code Guidelines

| Code | When to Use | Example |
|------|-------------|---------|
| 400 | Invalid request or operation | Restoring non-deleted item |
| 401 | Authentication required | Missing or invalid token |
| 403 | Permission denied | User lacks required role |
| 404 | Resource not found | Product with ID doesn't exist |
| 409 | Conflict with current state | Deleting category with products |
| 422 | Validation or business logic error | Invalid input data |
| 500 | Server error | Database connection failed |

## Best Practices Summary

1. **Never catch exceptions in controllers** - Let them propagate to the global handler

2. **Use specific exception types** - `BusinessException` for business rules, let Laravel handle the rest

3. **Include actionable messages** - "Cannot delete category with products. Please move or delete products first."

4. **Log before throwing** - Include context for debugging

5. **Test at multiple layers** - Unit tests for services, integration tests for HTTP

6. **Keep services focused** - They throw exceptions, handler formats responses

7. **Document your error types** - Help frontend developers handle errors correctly

## Conclusion

Centralized exception handling is not just about clean code—it's about building maintainable, testable, and consistent APIs. By separating error formatting from business logic, you create a system that's:

- **Easier to maintain** - Change error format in one place
- **More testable** - Test business logic separately from HTTP concerns
- **More consistent** - All errors follow the same format
- **More secure** - Central control over what information is exposed
- **More debuggable** - Central logging and monitoring

The initial investment in setting up centralized handling pays dividends throughout the lifecycle of your application. Start with a simple `BusinessException` class and a well-configured exception handler, and you'll have a solid foundation for building robust APIs.

---

*This article is based on the exception handling implementation in the SmartStockManagement project, demonstrating real-world patterns for Laravel 11 API development.*
