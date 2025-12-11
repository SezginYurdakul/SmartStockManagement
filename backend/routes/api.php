<?php

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
    });
});
