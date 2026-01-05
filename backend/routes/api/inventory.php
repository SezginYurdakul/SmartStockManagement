<?php

use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Management Routes
|--------------------------------------------------------------------------
| Warehouses, Stock, Stock Movements
*/

// Warehouses
Route::prefix('warehouses')->group(function () {
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::get('/list', [WarehouseController::class, 'list']);
        Route::get('/quarantine-zones', [WarehouseController::class, 'quarantineZones']);
        Route::get('/rejection-zones', [WarehouseController::class, 'rejectionZones']);
        Route::get('/qc-zones', [WarehouseController::class, 'qcZones']);
        Route::get('/{warehouse}', [WarehouseController::class, 'show']);
        Route::get('/{warehouse}/stock-summary', [WarehouseController::class, 'stockSummary']);
    });

    Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:inventory.create');

    Route::middleware('permission:inventory.edit')->group(function () {
        Route::put('/{warehouse}', [WarehouseController::class, 'update']);
        Route::post('/{warehouse}/toggle-active', [WarehouseController::class, 'toggleActive']);
        Route::post('/{warehouse}/set-default', [WarehouseController::class, 'setDefault']);
    });

    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:inventory.delete');
});

// Stock
Route::prefix('stock')->group(function () {
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('/low-stock', [StockController::class, 'lowStock']);
        Route::get('/expiring', [StockController::class, 'expiring']);
        Route::get('/product/{productId}', [StockController::class, 'productStock']);
        Route::get('/warehouse/{warehouseId}', [StockController::class, 'warehouseStock']);
    });

    Route::post('/receive', [StockController::class, 'receive'])->middleware('permission:inventory.create');

    Route::middleware('permission:inventory.edit')->group(function () {
        Route::post('/issue', [StockController::class, 'issue']);
        Route::post('/transfer', [StockController::class, 'transfer']);
        Route::post('/adjust', [StockController::class, 'adjust']);
        Route::post('/reserve', [StockController::class, 'reserve']);
        Route::post('/release-reservation', [StockController::class, 'releaseReservation']);
    });
});

// Stock Movements
Route::prefix('stock-movements')->middleware('permission:inventory.view')->group(function () {
    Route::get('/', [StockMovementController::class, 'index']);
    Route::get('/summary', [StockMovementController::class, 'summary']);
    Route::get('/daily-report', [StockMovementController::class, 'dailyReport']);
    Route::get('/audit-trail', [StockMovementController::class, 'auditTrail']);
    Route::get('/product/{productId}', [StockMovementController::class, 'productMovements']);
    Route::get('/warehouse/{warehouseId}', [StockMovementController::class, 'warehouseMovements']);
    Route::get('/types/movement', [StockMovementController::class, 'movementTypes']);
    Route::get('/types/transaction', [StockMovementController::class, 'transactionTypes']);
});
