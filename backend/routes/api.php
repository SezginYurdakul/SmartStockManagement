<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return response()->json([
        "message" => "API is working",
        "version" => "1.0.0",
        "status" => "active"
    ]);
});

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected Routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // User management routes (permission-based)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:users.delete');
        Route::delete('/{id}/force', [UserController::class, 'forceDelete'])->middleware('role:admin');
    });

    // Role management routes (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{role}/permissions/assign', [RoleController::class, 'assignPermissions']);
        Route::post('roles/{role}/permissions/revoke', [RoleController::class, 'revokePermissions']);

        // Permission management routes (Admin only)
        Route::apiResource('permissions', PermissionController::class);
        Route::get('permissions/modules/list', [PermissionController::class, 'modules']);
    });

    // Category routes (permission-based)
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view');
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create');
        Route::get('/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
        Route::put('/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

        // Category attribute management
        Route::get('/{category}/attributes', [CategoryController::class, 'getAttributes'])->middleware('permission:categories.view');
        Route::post('/{category}/attributes', [CategoryController::class, 'assignAttributes'])->middleware('permission:categories.edit');
        Route::put('/{category}/attributes/{attribute}', [CategoryController::class, 'updateAttribute'])->middleware('permission:categories.edit');
        Route::delete('/{category}/attributes/{attribute}', [CategoryController::class, 'removeAttribute'])->middleware('permission:categories.edit');
    });

    // Product routes (permission-based)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::get('/search', [ProductController::class, 'search'])->middleware('permission:products.view');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        // Product image routes
        Route::post('/{product}/images', [ProductImageController::class, 'upload'])->middleware('permission:products.edit');
        Route::put('/{product}/images/{image}', [ProductImageController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('/{product}/images/{image}', [ProductImageController::class, 'destroy'])->middleware('permission:products.edit');
        Route::post('/{product}/images/reorder', [ProductImageController::class, 'reorder'])->middleware('permission:products.edit');

        // Product attribute management
        Route::get('/{product}/attributes', [ProductController::class, 'getAttributes'])->middleware('permission:products.view');
        Route::post('/{product}/attributes', [ProductController::class, 'assignAttributes'])->middleware('permission:products.edit');
        Route::put('/{product}/attributes/{attribute}', [ProductController::class, 'updateAttribute'])->middleware('permission:products.edit');
        Route::delete('/{product}/attributes/{attribute}', [ProductController::class, 'removeAttribute'])->middleware('permission:products.edit');

        // Product variant management
        Route::get('/{product}/variants', [ProductController::class, 'getVariants'])->middleware('permission:products.view');
        Route::post('/{product}/variants', [ProductController::class, 'createVariant'])->middleware('permission:products.edit');

        // Automatic variant generation (rate limited: 10 requests per minute)
        // NOTE: These specific routes MUST come BEFORE the {variant} parameter routes
        Route::post('/{product}/variants/generate', [AttributeController::class, 'generateVariants'])
            ->middleware(['permission:products.edit', 'throttle:variant-generate']);
        Route::delete('/{product}/variants/clear', [AttributeController::class, 'clearVariants'])
            ->middleware('permission:products.edit');

        // Variant CRUD with ID parameter (must come AFTER specific routes like /generate and /clear)
        Route::put('/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])->middleware('permission:products.edit');
        Route::delete('/{product}/variants/{variant}', [ProductController::class, 'deleteVariant'])->middleware('permission:products.edit');

        // Force delete variants (Admin only - permanent deletion)
        Route::delete('/{product}/variants/{variant}/force', [ProductController::class, 'forceDeleteVariant'])->middleware('role:admin');
        Route::delete('/{product}/variants/force-clear', [AttributeController::class, 'forceClearVariants'])->middleware('role:admin');
    });

    // Attribute routes (permission-based)
    Route::prefix('attributes')->group(function () {
        Route::get('/', [AttributeController::class, 'index'])->middleware('permission:products.view');
        Route::post('/', [AttributeController::class, 'store'])->middleware('permission:products.create');
        Route::get('/{attribute}', [AttributeController::class, 'show'])->middleware('permission:products.view');
        Route::put('/{attribute}', [AttributeController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('/{attribute}', [AttributeController::class, 'destroy'])->middleware('permission:products.delete');

        // Attribute value management
        Route::post('/{attribute}/values', [AttributeController::class, 'addValues'])->middleware('permission:products.edit');
        Route::put('/{attribute}/values/{value}', [AttributeController::class, 'updateValue'])->middleware('permission:products.edit');
        Route::delete('/{attribute}/values/{value}', [AttributeController::class, 'destroyValue'])->middleware('permission:products.edit');
    });

    // Bulk variant generation (rate limited: 5 requests per minute - heavy operation)
    Route::post('/variants/bulk-generate', [AttributeController::class, 'bulkGenerateVariants'])
        ->middleware(['permission:products.edit', 'throttle:bulk-variant-generate']);
});
