<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\ProductUomConversionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AttributeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product Management Routes
|--------------------------------------------------------------------------
| Products, Categories, Attributes, Product Types, Variants
*/

// Categories
Route::prefix('categories')->group(function () {
    Route::middleware('permission:categories.view')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::get('/{category}/attributes', [CategoryController::class, 'getAttributes']);
    });

    Route::middleware('permission:categories.create')->post('/', [CategoryController::class, 'store']);

    Route::middleware('permission:categories.edit')->group(function () {
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::post('/{category}/attributes', [CategoryController::class, 'assignAttributes']);
        Route::put('/{category}/attributes/{attribute}', [CategoryController::class, 'updateAttribute']);
        Route::delete('/{category}/attributes/{attribute}', [CategoryController::class, 'removeAttribute']);
    });

    Route::middleware('permission:categories.delete')->delete('/{category}', [CategoryController::class, 'destroy']);
});

// Product Types
Route::prefix('producttypes')->group(function () {
    Route::middleware('permission:producttypes.view')->group(function () {
        Route::get('/', [ProductTypeController::class, 'index']);
        Route::get('/{productType}', [ProductTypeController::class, 'show']);
    });

    Route::post('/', [ProductTypeController::class, 'store'])->middleware('permission:producttypes.create');
    Route::put('/{productType}', [ProductTypeController::class, 'update'])->middleware('permission:producttypes.edit');
    Route::delete('/{productType}', [ProductTypeController::class, 'destroy'])->middleware('permission:producttypes.delete');
});

// Attributes
Route::prefix('attributes')->group(function () {
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/', [AttributeController::class, 'index']);
        Route::get('/{attribute}', [AttributeController::class, 'show']);
    });

    Route::post('/', [AttributeController::class, 'store'])->middleware('permission:products.create');

    Route::middleware('permission:products.edit')->group(function () {
        Route::put('/{attribute}', [AttributeController::class, 'update']);
        Route::post('/{attribute}/values', [AttributeController::class, 'addValues']);
        Route::put('/{attribute}/values/{value}', [AttributeController::class, 'updateValue']);
        Route::delete('/{attribute}/values/{value}', [AttributeController::class, 'destroyValue']);
    });

    Route::delete('/{attribute}', [AttributeController::class, 'destroy'])->middleware('permission:products.delete');
});

// Bulk variant generation
Route::post('/variants/bulk-generate', [AttributeController::class, 'bulkGenerateVariants'])
    ->middleware(['permission:products.edit', 'throttle:bulk-variant-generate']);

// Products
Route::prefix('products')->group(function () {
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/query', [ProductController::class, 'search']);
        Route::get('/find', [ProductController::class, 'search']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::get('/{product}/attributes', [ProductController::class, 'getAttributes']);
        Route::get('/{product}/variants', [ProductController::class, 'getVariants']);
        Route::get('/{product}/uom-conversions', [ProductUomConversionController::class, 'index']);
        Route::get('/{product}/uom-conversions/{conversion}', [ProductUomConversionController::class, 'show']);
        Route::post('/{product}/uom-conversions/convert', [ProductUomConversionController::class, 'convert']);
    });

    Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create');

    Route::middleware('permission:products.edit')->group(function () {
        Route::put('/{product}', [ProductController::class, 'update']);

        // Product images
        Route::post('/{product}/images', [ProductImageController::class, 'upload']);
        Route::put('/{product}/images/{image}', [ProductImageController::class, 'update']);
        Route::delete('/{product}/images/{image}', [ProductImageController::class, 'destroy']);
        Route::post('/{product}/images/reorder', [ProductImageController::class, 'reorder']);

        // Product attributes
        Route::post('/{product}/attributes', [ProductController::class, 'assignAttributes']);
        Route::put('/{product}/attributes/{attribute}', [ProductController::class, 'updateAttribute']);
        Route::delete('/{product}/attributes/{attribute}', [ProductController::class, 'removeAttribute']);

        // Product variants
        Route::post('/{product}/variants', [ProductController::class, 'createVariant']);
        Route::put('/{product}/variants/{variant}', [ProductController::class, 'updateVariant']);
        Route::delete('/{product}/variants/{variant}', [ProductController::class, 'deleteVariant']);

        // Variant generation (rate limited)
        Route::post('/{product}/variants/generate', [AttributeController::class, 'generateVariants'])
            ->middleware('throttle:variant-generate');
        Route::post('/{product}/variants/expand', [AttributeController::class, 'expandVariants'])
            ->middleware('throttle:variant-generate');
        Route::delete('/{product}/variants/clear', [AttributeController::class, 'clearVariants']);

        // Product UOM conversions
        Route::post('/{product}/uom-conversions', [ProductUomConversionController::class, 'store']);
        Route::post('/{product}/uom-conversions/bulk', [ProductUomConversionController::class, 'bulkStore']);
        Route::post('/{product}/uom-conversions/copy-from', [ProductUomConversionController::class, 'copyFrom']);
        Route::put('/{product}/uom-conversions/{conversion}', [ProductUomConversionController::class, 'update']);
        Route::delete('/{product}/uom-conversions/{conversion}', [ProductUomConversionController::class, 'destroy']);
        Route::post('/{product}/uom-conversions/{conversion}/toggle-active', [ProductUomConversionController::class, 'toggleActive']);
    });

    Route::middleware('permission:products.delete')->group(function () {
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::post('/{id}/restore', [ProductController::class, 'restore']);
    });

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::delete('/{product}/variants/{variant}/force', [ProductController::class, 'forceDeleteVariant']);
        Route::delete('/{product}/variants/force-clear', [AttributeController::class, 'forceClearVariants']);
    });
});
