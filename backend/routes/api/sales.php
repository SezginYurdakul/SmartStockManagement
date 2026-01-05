<?php

use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\DeliveryNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales Module Routes
|--------------------------------------------------------------------------
| Customer Groups, Customers, Sales Orders, Delivery Notes
| Requires: MODULE_SALES_ENABLED=true
*/

// Customer Groups
Route::prefix('customer-groups')->group(function () {
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/', [CustomerGroupController::class, 'index']);
        Route::get('/list', [CustomerGroupController::class, 'list']);
        Route::get('/{customerGroup}', [CustomerGroupController::class, 'show']);
        Route::get('/{customerGroup}/prices', [CustomerGroupController::class, 'prices']);
    });

    Route::post('/', [CustomerGroupController::class, 'store'])->middleware('permission:customers.create');

    Route::middleware('permission:customers.edit')->group(function () {
        Route::put('/{customerGroup}', [CustomerGroupController::class, 'update']);
        Route::post('/{customerGroup}/prices', [CustomerGroupController::class, 'setPrice']);
        Route::post('/{customerGroup}/prices/bulk', [CustomerGroupController::class, 'bulkSetPrices']);
        Route::delete('/{customerGroup}/prices/{priceId}', [CustomerGroupController::class, 'deletePrice']);
    });

    Route::delete('/{customerGroup}', [CustomerGroupController::class, 'destroy'])->middleware('permission:customers.delete');
});

// Customers
Route::prefix('customers')->group(function () {
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/list', [CustomerController::class, 'list']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::get('/{customer}/statistics', [CustomerController::class, 'statistics']);
    });

    Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::put('/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.edit');
    Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
});

// Sales Orders
Route::prefix('sales-orders')->group(function () {
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('/', [SalesOrderController::class, 'index']);
        Route::get('/statistics', [SalesOrderController::class, 'statistics']);
        Route::get('/statuses', [SalesOrderController::class, 'statuses']);
        Route::get('/{salesOrder}', [SalesOrderController::class, 'show']);
    });

    Route::post('/', [SalesOrderController::class, 'store'])->middleware('permission:sales.create');

    Route::middleware('permission:sales.edit')->group(function () {
        Route::put('/{salesOrder}', [SalesOrderController::class, 'update']);
        Route::post('/{salesOrder}/submit-for-approval', [SalesOrderController::class, 'submitForApproval']);
        Route::post('/{salesOrder}/confirm', [SalesOrderController::class, 'confirm']);
        Route::post('/{salesOrder}/cancel', [SalesOrderController::class, 'cancel']);
    });

    Route::middleware('permission:sales.approve')->group(function () {
        Route::post('/{salesOrder}/approve', [SalesOrderController::class, 'approve']);
        Route::post('/{salesOrder}/reject', [SalesOrderController::class, 'reject']);
    });

    Route::middleware('permission:sales.ship')->group(function () {
        Route::post('/{salesOrder}/mark-as-shipped', [SalesOrderController::class, 'markAsShipped']);
        Route::post('/{salesOrder}/mark-as-delivered', [SalesOrderController::class, 'markAsDelivered']);
    });

    Route::delete('/{salesOrder}', [SalesOrderController::class, 'destroy'])->middleware('permission:sales.delete');
});

// Delivery Notes
Route::prefix('delivery-notes')->group(function () {
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('/', [DeliveryNoteController::class, 'index']);
        Route::get('/statuses', [DeliveryNoteController::class, 'statuses']);
        Route::get('/for-sales-order/{salesOrder}', [DeliveryNoteController::class, 'forSalesOrder']);
        Route::get('/{deliveryNote}', [DeliveryNoteController::class, 'show']);
    });

    Route::post('/', [DeliveryNoteController::class, 'store'])->middleware('permission:sales.create');
    Route::put('/{deliveryNote}', [DeliveryNoteController::class, 'update'])->middleware('permission:sales.edit');

    Route::middleware('permission:sales.ship')->group(function () {
        Route::post('/{deliveryNote}/confirm', [DeliveryNoteController::class, 'confirm']);
        Route::post('/{deliveryNote}/ship', [DeliveryNoteController::class, 'ship']);
        Route::post('/{deliveryNote}/mark-as-delivered', [DeliveryNoteController::class, 'markAsDelivered']);
        Route::post('/{deliveryNote}/cancel', [DeliveryNoteController::class, 'cancel']);
    });

    Route::delete('/{deliveryNote}', [DeliveryNoteController::class, 'destroy'])->middleware('permission:sales.delete');
});
