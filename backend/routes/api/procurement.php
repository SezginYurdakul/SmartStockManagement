<?php

use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceivedNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Module Routes
|--------------------------------------------------------------------------
| Suppliers, Purchase Orders, Goods Received Notes
| Requires: MODULE_PROCUREMENT_ENABLED=true
*/

// Suppliers
Route::prefix('suppliers')->group(function () {
    Route::middleware('permission:purchasing.view')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);
        Route::get('/list', [SupplierController::class, 'list']);
        Route::get('/for-product/{productId}', [SupplierController::class, 'forProduct']);
        
        // Supplier Quality routes (requires QC module) - MUST be before /{supplier} route
        Route::middleware('module:qc')->group(function () {
            Route::get('/quality-ranking', [SupplierController::class, 'qualityRanking'])->middleware('permission:qc.view');
        });
        
        Route::get('/{supplier}', [SupplierController::class, 'show']);
        Route::get('/{supplier}/statistics', [SupplierController::class, 'statistics']);
    });

    // Supplier Quality routes (requires QC module) - per supplier routes
    Route::middleware('module:qc')->group(function () {
        Route::get('/{supplier}/quality-score', [SupplierController::class, 'qualityScore'])->middleware('permission:qc.view');
        Route::get('/{supplier}/quality-statistics', [SupplierController::class, 'qualityStatistics'])->middleware('permission:qc.view');
    });

    Route::post('/', [SupplierController::class, 'store'])->middleware('permission:purchasing.create');

    Route::middleware('permission:purchasing.edit')->group(function () {
        Route::put('/{supplier}', [SupplierController::class, 'update']);
        Route::post('/{supplier}/toggle-active', [SupplierController::class, 'toggleActive']);
        Route::post('/{supplier}/products', [SupplierController::class, 'attachProducts']);
        Route::put('/{supplier}/products/{productId}', [SupplierController::class, 'updateProduct']);
        Route::delete('/{supplier}/products/{productId}', [SupplierController::class, 'detachProduct']);
    });

    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:purchasing.delete');
});

// Purchase Orders
Route::prefix('purchase-orders')->group(function () {
    Route::middleware('permission:purchasing.view')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::get('/statistics', [PurchaseOrderController::class, 'statistics']);
        Route::get('/overdue', [PurchaseOrderController::class, 'overdue']);
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });

    Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('permission:purchasing.create');

    Route::middleware('permission:purchasing.edit')->group(function () {
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::post('/{purchaseOrder}/items', [PurchaseOrderController::class, 'addItems']);
        Route::put('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'updateItem']);
        Route::delete('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'deleteItem']);
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submitForApproval']);
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'markAsSent']);
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('/{purchaseOrder}/close', [PurchaseOrderController::class, 'close']);
    });

    Route::middleware('permission:purchasing.approve')->group(function () {
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
    });

    Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchasing.delete');
});

// Goods Received Notes (GRN)
Route::prefix('goods-received-notes')->group(function () {
    Route::middleware('permission:purchasing.view')->group(function () {
        Route::get('/', [GoodsReceivedNoteController::class, 'index']);
        Route::get('/pending-inspection', [GoodsReceivedNoteController::class, 'pendingInspection']);
        Route::get('/for-purchase-order/{purchaseOrderId}', [GoodsReceivedNoteController::class, 'forPurchaseOrder']);
        Route::get('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'show']);
    });

    Route::middleware('permission:purchasing.receive')->group(function () {
        Route::post('/', [GoodsReceivedNoteController::class, 'store']);
        Route::put('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'update']);
        Route::delete('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'destroy']);
        Route::post('/{goodsReceivedNote}/submit-inspection', [GoodsReceivedNoteController::class, 'submitForInspection']);
        Route::post('/{goodsReceivedNote}/complete', [GoodsReceivedNoteController::class, 'complete']);
        Route::post('/{goodsReceivedNote}/cancel', [GoodsReceivedNoteController::class, 'cancel']);
    });

    Route::post('/{goodsReceivedNote}/record-inspection', [GoodsReceivedNoteController::class, 'recordInspection'])
        ->middleware('permission:purchasing.inspect');
});
