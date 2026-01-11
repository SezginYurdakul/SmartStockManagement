<?php

use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\MrpController;
use App\Http\Controllers\CapacityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing Module Routes
|--------------------------------------------------------------------------
| Work Centers, BOMs, Routings, Work Orders, MRP, Capacity Planning
| Requires: MODULE_MANUFACTURING_ENABLED=true
*/

// Work Centers
Route::prefix('work-centers')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/', [WorkCenterController::class, 'index']);
        Route::get('/list', [WorkCenterController::class, 'list']);
        Route::get('/types', [WorkCenterController::class, 'types']);
        Route::get('/{workCenter}', [WorkCenterController::class, 'show']);
        Route::get('/{workCenter}/availability', [WorkCenterController::class, 'availability']);
    });

    Route::post('/', [WorkCenterController::class, 'store'])->middleware('permission:manufacturing.create');

    Route::middleware('permission:manufacturing.edit')->group(function () {
        Route::put('/{workCenter}', [WorkCenterController::class, 'update']);
        Route::post('/{workCenter}/toggle-active', [WorkCenterController::class, 'toggleActive']);
    });

    Route::delete('/{workCenter}', [WorkCenterController::class, 'destroy'])->middleware('permission:manufacturing.delete');
});

// BOMs (Bill of Materials)
Route::prefix('boms')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/', [BomController::class, 'index']);
        Route::get('/list', [BomController::class, 'list']);
        Route::get('/types', [BomController::class, 'types']);
        Route::get('/statuses', [BomController::class, 'statuses']);
        Route::get('/for-product/{productId}', [BomController::class, 'forProduct']);
        Route::get('/{bom}', [BomController::class, 'show']);
        Route::match(['get', 'post'], '/{bom}/explode', [BomController::class, 'explode']);
    });

    Route::post('/', [BomController::class, 'store'])->middleware('permission:manufacturing.create');
    Route::post('/{bom}/copy', [BomController::class, 'copy'])->middleware('permission:manufacturing.create');

    Route::middleware('permission:manufacturing.edit')->group(function () {
        Route::put('/{bom}', [BomController::class, 'update']);
        Route::post('/{bom}/items', [BomController::class, 'addItem']);
        Route::put('/{bom}/items/{itemId}', [BomController::class, 'updateItem']);
        Route::delete('/{bom}/items/{itemId}', [BomController::class, 'removeItem']);
        Route::post('/{bom}/activate', [BomController::class, 'activate']);
        Route::post('/{bom}/obsolete', [BomController::class, 'obsolete']);
        Route::post('/{bom}/set-default', [BomController::class, 'setDefault']);
    });

    Route::delete('/{bom}', [BomController::class, 'destroy'])->middleware('permission:manufacturing.delete');
});

// Routings
Route::prefix('routings')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/', [RoutingController::class, 'index']);
        Route::get('/list', [RoutingController::class, 'list']);
        Route::get('/statuses', [RoutingController::class, 'statuses']);
        Route::get('/for-product/{productId}', [RoutingController::class, 'forProduct']);
        Route::get('/{routing}', [RoutingController::class, 'show']);
        Route::post('/{routing}/calculate-lead-time', [RoutingController::class, 'calculateLeadTime']);
    });

    Route::post('/', [RoutingController::class, 'store'])->middleware('permission:manufacturing.create');
    Route::post('/{routing}/copy', [RoutingController::class, 'copy'])->middleware('permission:manufacturing.create');

    Route::middleware('permission:manufacturing.edit')->group(function () {
        Route::put('/{routing}', [RoutingController::class, 'update']);
        Route::post('/{routing}/operations', [RoutingController::class, 'addOperation']);
        Route::put('/{routing}/operations/{operationId}', [RoutingController::class, 'updateOperation']);
        Route::delete('/{routing}/operations/{operationId}', [RoutingController::class, 'removeOperation']);
        Route::post('/{routing}/operations/reorder', [RoutingController::class, 'reorderOperations']);
        Route::post('/{routing}/activate', [RoutingController::class, 'activate']);
        Route::post('/{routing}/obsolete', [RoutingController::class, 'obsolete']);
        Route::post('/{routing}/set-default', [RoutingController::class, 'setDefault']);
    });

    Route::delete('/{routing}', [RoutingController::class, 'destroy'])->middleware('permission:manufacturing.delete');
});

// Work Orders
Route::prefix('work-orders')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index']);
        Route::get('/statistics', [WorkOrderController::class, 'statistics']);
        Route::get('/statuses', [WorkOrderController::class, 'statuses']);
        Route::get('/priorities', [WorkOrderController::class, 'priorities']);
        Route::get('/{workOrder}', [WorkOrderController::class, 'show']);
        Route::get('/{workOrder}/material-requirements', [WorkOrderController::class, 'materialRequirements']);
        Route::get('/{workOrder}/check-capacity', [CapacityController::class, 'checkWorkOrderCapacity']);
    });

    Route::post('/', [WorkOrderController::class, 'store'])->middleware('permission:manufacturing.create');

    Route::middleware('permission:manufacturing.edit')->group(function () {
        Route::put('/{workOrder}', [WorkOrderController::class, 'update']);
        Route::post('/{workOrder}/start', [WorkOrderController::class, 'start']);
        Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
        Route::post('/{workOrder}/hold', [WorkOrderController::class, 'hold']);
        Route::post('/{workOrder}/resume', [WorkOrderController::class, 'resume']);
        Route::post('/{workOrder}/operations/{operationId}/start', [WorkOrderController::class, 'startOperation']);
        Route::post('/{workOrder}/issue-materials', [WorkOrderController::class, 'issueMaterials']);
    });

    Route::post('/{workOrder}/release', [WorkOrderController::class, 'release'])->middleware('permission:manufacturing.release');

    Route::middleware('permission:manufacturing.complete')->group(function () {
        Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete']);
        Route::post('/{workOrder}/operations/{operationId}/complete', [WorkOrderController::class, 'completeOperation']);
        Route::post('/{workOrder}/receive-finished-goods', [WorkOrderController::class, 'receiveFinishedGoods']);
    });

    Route::delete('/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('permission:manufacturing.delete');
});

// MRP (Material Requirements Planning)
Route::prefix('mrp')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/', [MrpController::class, 'index']);
        Route::get('/statistics', [MrpController::class, 'statistics']);
        Route::get('/products-needing-attention', [MrpController::class, 'productsNeedingAttention']);
        Route::get('/statuses', [MrpController::class, 'statuses']);
        Route::get('/recommendation-types', [MrpController::class, 'recommendationTypes']);
        Route::get('/recommendation-statuses', [MrpController::class, 'recommendationStatuses']);
        Route::get('/priorities', [MrpController::class, 'priorities']);
        Route::get('/{mrpRun}', [MrpController::class, 'show']);
        Route::get('/{mrpRun}/progress', [MrpController::class, 'progress']);
        Route::get('/{mrpRun}/recommendations', [MrpController::class, 'recommendations']);
    });

    Route::middleware('permission:manufacturing.mrp')->group(function () {
        Route::post('/', [MrpController::class, 'store']);
        Route::post('/invalidate-cache', [MrpController::class, 'invalidateCache']);
        Route::post('/{mrpRun}/cancel', [MrpController::class, 'cancel']);
        Route::post('/recommendations/bulk-approve', [MrpController::class, 'bulkApprove']);
        Route::post('/recommendations/bulk-reject', [MrpController::class, 'bulkReject']);
        Route::post('/recommendations/{recommendation}/approve', [MrpController::class, 'approveRecommendation']);
        Route::post('/recommendations/{recommendation}/reject', [MrpController::class, 'rejectRecommendation']);
    });
});

// Capacity Planning (CRP)
Route::prefix('capacity')->group(function () {
    Route::middleware('permission:manufacturing.view')->group(function () {
        Route::get('/overview', [CapacityController::class, 'overview']);
        Route::get('/load-report', [CapacityController::class, 'loadReport']);
        Route::get('/bottleneck-analysis', [CapacityController::class, 'bottleneckAnalysis']);
        Route::get('/day-types', [CapacityController::class, 'dayTypes']);
        Route::get('/work-center/{workCenter}', [CapacityController::class, 'workCenterCapacity']);
        Route::get('/work-center/{workCenter}/daily', [CapacityController::class, 'dailyCapacity']);
        Route::get('/work-center/{workCenter}/find-slot', [CapacityController::class, 'findSlot']);
        Route::get('/work-center/{workCenter}/calendar', [CapacityController::class, 'calendar']);
    });

    Route::middleware('permission:manufacturing.edit')->group(function () {
        Route::post('/generate-calendar', [CapacityController::class, 'generateCalendar']);
        Route::post('/work-center/{workCenter}/set-holiday', [CapacityController::class, 'setHoliday']);
        Route::post('/work-center/{workCenter}/set-maintenance', [CapacityController::class, 'setMaintenance']);
        Route::put('/calendar/{calendar}', [CapacityController::class, 'updateCalendarEntry']);
    });
});
