<?php

use App\Http\Controllers\AcceptanceRuleController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\GoodsReceivedNoteController;
use App\Http\Controllers\NonConformanceReportController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceivingInspectionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return response()->json([
        "message" => "API is working",
        "version" => "1.0.0",
        "status" => "active"
    ]);
});

// Module status endpoint (public - for health checks)
Route::get("/modules", function () {
    return response()->json(
        app(\App\Services\ModuleService::class)->getModuleStatus()
    );
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

        // Search endpoints - multiple URL options for flexibility
        // All accept: ?search=, ?query=, or ?q= parameters
        Route::get('/search', [ProductController::class, 'search'])->middleware('permission:products.view');
        Route::get('/query', [ProductController::class, 'search'])->middleware('permission:products.view');
        Route::get('/find', [ProductController::class, 'search'])->middleware('permission:products.view');

        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
        Route::post('/{id}/restore', [ProductController::class, 'restore'])->middleware('permission:products.delete');

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
        Route::post('/{product}/variants/expand', [AttributeController::class, 'expandVariants'])
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

    // Product types routes (permission-based)
    Route::prefix('producttypes')->group(function () {
        Route::get('/', [ProductTypeController::class, 'index'])->middleware('permission:producttypes.view');
        Route::get('/{productType}', [ProductTypeController::class, 'show'])->middleware('permission:producttypes.view');
        Route::post('/', [ProductTypeController::class, 'store'])->middleware('permission:producttypes.create');
        Route::put('/{productType}', [ProductTypeController::class, 'update'])->middleware('permission:producttypes.edit');
        Route::delete('/{productType}', [ProductTypeController::class, 'destroy'])->middleware('permission:producttypes.delete');
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

    // Settings routes (lookup values, system config)
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->middleware('permission:settings.view');
        Route::get('/groups', [SettingController::class, 'groups'])->middleware('permission:settings.view');
        Route::get('/group/{group}', [SettingController::class, 'group'])->middleware('permission:settings.view');
        Route::get('/{group}/{key}', [SettingController::class, 'show'])->middleware('permission:settings.view');
        Route::post('/', [SettingController::class, 'store'])->middleware('permission:settings.edit');
        Route::put('/{group}/{key}', [SettingController::class, 'update'])->middleware('permission:settings.edit');
        Route::delete('/{group}/{key}', [SettingController::class, 'destroy'])->middleware('permission:settings.edit');
    });

    // Currency routes
    Route::prefix('currencies')->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->middleware('permission:settings.view');
        Route::get('/active', [CurrencyController::class, 'active'])->middleware('permission:settings.view');
        Route::post('/', [CurrencyController::class, 'store'])->middleware('permission:settings.edit');
        Route::get('/{currency}', [CurrencyController::class, 'show'])->middleware('permission:settings.view');
        Route::put('/{currency}', [CurrencyController::class, 'update'])->middleware('permission:settings.edit');
        Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->middleware('permission:settings.edit');
        Route::post('/{currency}/toggle-active', [CurrencyController::class, 'toggleActive'])->middleware('permission:settings.edit');

        // Exchange rate management
        Route::get('/exchange-rate/get', [CurrencyController::class, 'getExchangeRate'])->middleware('permission:settings.view');
        Route::post('/exchange-rate/set', [CurrencyController::class, 'setExchangeRate'])->middleware('permission:settings.edit');
        Route::get('/exchange-rate/history', [CurrencyController::class, 'exchangeRateHistory'])->middleware('permission:settings.view');
        Route::post('/convert', [CurrencyController::class, 'convert'])->middleware('permission:settings.view');
    });

    // Warehouse routes
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/list', [WarehouseController::class, 'list'])->middleware('permission:inventory.view');
        Route::get('/quarantine-zones', [WarehouseController::class, 'quarantineZones'])->middleware('permission:inventory.view');
        Route::get('/rejection-zones', [WarehouseController::class, 'rejectionZones'])->middleware('permission:inventory.view');
        Route::get('/qc-zones', [WarehouseController::class, 'qcZones'])->middleware('permission:inventory.view');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:inventory.create');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:inventory.view');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:inventory.edit');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:inventory.delete');
        Route::post('/{warehouse}/toggle-active', [WarehouseController::class, 'toggleActive'])->middleware('permission:inventory.edit');
        Route::post('/{warehouse}/set-default', [WarehouseController::class, 'setDefault'])->middleware('permission:inventory.edit');
        Route::get('/{warehouse}/stock-summary', [WarehouseController::class, 'stockSummary'])->middleware('permission:inventory.view');
    });

    // Stock routes
    Route::prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/low-stock', [StockController::class, 'lowStock'])->middleware('permission:inventory.view');
        Route::get('/expiring', [StockController::class, 'expiring'])->middleware('permission:inventory.view');
        Route::get('/product/{productId}', [StockController::class, 'productStock'])->middleware('permission:inventory.view');
        Route::get('/warehouse/{warehouseId}', [StockController::class, 'warehouseStock'])->middleware('permission:inventory.view');

        // Stock operations
        Route::post('/receive', [StockController::class, 'receive'])->middleware('permission:inventory.create');
        Route::post('/issue', [StockController::class, 'issue'])->middleware('permission:inventory.edit');
        Route::post('/transfer', [StockController::class, 'transfer'])->middleware('permission:inventory.edit');
        Route::post('/adjust', [StockController::class, 'adjust'])->middleware('permission:inventory.edit');
        Route::post('/reserve', [StockController::class, 'reserve'])->middleware('permission:inventory.edit');
        Route::post('/release-reservation', [StockController::class, 'releaseReservation'])->middleware('permission:inventory.edit');
    });

    // Stock Movement routes
    Route::prefix('stock-movements')->group(function () {
        Route::get('/', [StockMovementController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('/summary', [StockMovementController::class, 'summary'])->middleware('permission:inventory.view');
        Route::get('/daily-report', [StockMovementController::class, 'dailyReport'])->middleware('permission:inventory.view');
        Route::get('/audit-trail', [StockMovementController::class, 'auditTrail'])->middleware('permission:inventory.view');
        Route::get('/product/{productId}', [StockMovementController::class, 'productMovements'])->middleware('permission:inventory.view');
        Route::get('/warehouse/{warehouseId}', [StockMovementController::class, 'warehouseMovements'])->middleware('permission:inventory.view');
        Route::get('/types/movement', [StockMovementController::class, 'movementTypes'])->middleware('permission:inventory.view');
        Route::get('/types/transaction', [StockMovementController::class, 'transactionTypes'])->middleware('permission:inventory.view');
    });

    // ===================================================
    // PROCUREMENT MODULE (Phase 3)
    // Requires: MODULE_PROCUREMENT_ENABLED=true
    // ===================================================
    Route::middleware('module:procurement')->group(function () {

    // Supplier routes
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->middleware('permission:purchasing.view');
        Route::get('/list', [SupplierController::class, 'list'])->middleware('permission:purchasing.view');
        Route::post('/', [SupplierController::class, 'store'])->middleware('permission:purchasing.create');
        Route::get('/for-product/{productId}', [SupplierController::class, 'forProduct'])->middleware('permission:purchasing.view');
        Route::get('/quality-ranking', [SupplierController::class, 'qualityRanking'])->middleware('permission:qc.view');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('permission:purchasing.edit');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:purchasing.delete');
        Route::post('/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])->middleware('permission:purchasing.edit');
        Route::get('/{supplier}/statistics', [SupplierController::class, 'statistics'])->middleware('permission:purchasing.view');
        Route::get('/{supplier}/quality-score', [SupplierController::class, 'qualityScore'])->middleware('permission:qc.view');
        Route::get('/{supplier}/quality-statistics', [SupplierController::class, 'qualityStatistics'])->middleware('permission:qc.view');

        // Supplier product management
        Route::post('/{supplier}/products', [SupplierController::class, 'attachProducts'])->middleware('permission:purchasing.edit');
        Route::put('/{supplier}/products/{productId}', [SupplierController::class, 'updateProduct'])->middleware('permission:purchasing.edit');
        Route::delete('/{supplier}/products/{productId}', [SupplierController::class, 'detachProduct'])->middleware('permission:purchasing.edit');
    });

    // Purchase Order routes
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('permission:purchasing.create');
        Route::get('/statistics', [PurchaseOrderController::class, 'statistics'])->middleware('permission:purchasing.view');
        Route::get('/overdue', [PurchaseOrderController::class, 'overdue'])->middleware('permission:purchasing.view');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchasing.edit');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchasing.delete');

        // PO Item management
        Route::post('/{purchaseOrder}/items', [PurchaseOrderController::class, 'addItems'])->middleware('permission:purchasing.edit');
        Route::put('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'updateItem'])->middleware('permission:purchasing.edit');
        Route::delete('/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'deleteItem'])->middleware('permission:purchasing.edit');

        // PO Workflow actions
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submitForApproval'])->middleware('permission:purchasing.edit');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:purchasing.approve');
        Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->middleware('permission:purchasing.approve');
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'markAsSent'])->middleware('permission:purchasing.edit');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchasing.edit');
        Route::post('/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->middleware('permission:purchasing.edit');
    });

    // Goods Received Note (GRN) routes
    Route::prefix('goods-received-notes')->group(function () {
        Route::get('/', [GoodsReceivedNoteController::class, 'index'])->middleware('permission:purchasing.view');
        Route::post('/', [GoodsReceivedNoteController::class, 'store'])->middleware('permission:purchasing.receive');
        Route::get('/pending-inspection', [GoodsReceivedNoteController::class, 'pendingInspection'])->middleware('permission:purchasing.view');
        Route::get('/for-purchase-order/{purchaseOrderId}', [GoodsReceivedNoteController::class, 'forPurchaseOrder'])->middleware('permission:purchasing.view');
        Route::get('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'show'])->middleware('permission:purchasing.view');
        Route::put('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'update'])->middleware('permission:purchasing.receive');
        Route::delete('/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'destroy'])->middleware('permission:purchasing.receive');

        // GRN Workflow actions
        Route::post('/{goodsReceivedNote}/submit-inspection', [GoodsReceivedNoteController::class, 'submitForInspection'])->middleware('permission:purchasing.receive');
        Route::post('/{goodsReceivedNote}/record-inspection', [GoodsReceivedNoteController::class, 'recordInspection'])->middleware('permission:purchasing.inspect');
        Route::post('/{goodsReceivedNote}/complete', [GoodsReceivedNoteController::class, 'complete'])->middleware('permission:purchasing.receive');
        Route::post('/{goodsReceivedNote}/cancel', [GoodsReceivedNoteController::class, 'cancel'])->middleware('permission:purchasing.receive');
    });

    // ===================================================
    // QUALITY CONTROL (QC) - Standard Level
    // Part of Procurement Module
    // ===================================================

    // Acceptance Rules routes
    Route::prefix('acceptance-rules')->group(function () {
        Route::get('/', [AcceptanceRuleController::class, 'index'])->middleware('permission:qc.view');
        Route::get('/list', [AcceptanceRuleController::class, 'list'])->middleware('permission:qc.view');
        Route::post('/', [AcceptanceRuleController::class, 'store'])->middleware('permission:qc.create');
        Route::get('/inspection-types', [AcceptanceRuleController::class, 'inspectionTypes'])->middleware('permission:qc.view');
        Route::get('/sampling-methods', [AcceptanceRuleController::class, 'samplingMethods'])->middleware('permission:qc.view');
        Route::post('/find-applicable', [AcceptanceRuleController::class, 'findApplicable'])->middleware('permission:qc.view');
        Route::get('/{acceptanceRule}', [AcceptanceRuleController::class, 'show'])->middleware('permission:qc.view');
        Route::put('/{acceptanceRule}', [AcceptanceRuleController::class, 'update'])->middleware('permission:qc.edit');
        Route::delete('/{acceptanceRule}', [AcceptanceRuleController::class, 'destroy'])->middleware('permission:qc.delete');
    });

    // Receiving Inspections routes
    Route::prefix('receiving-inspections')->group(function () {
        Route::get('/', [ReceivingInspectionController::class, 'index'])->middleware('permission:qc.view');
        Route::get('/statistics', [ReceivingInspectionController::class, 'statistics'])->middleware('permission:qc.view');
        Route::get('/results', [ReceivingInspectionController::class, 'results'])->middleware('permission:qc.view');
        Route::get('/dispositions', [ReceivingInspectionController::class, 'dispositions'])->middleware('permission:qc.view');
        Route::get('/for-grn/{goodsReceivedNote}', [ReceivingInspectionController::class, 'forGrn'])->middleware('permission:qc.view');
        Route::post('/create-for-grn/{goodsReceivedNote}', [ReceivingInspectionController::class, 'createForGrn'])->middleware('permission:qc.inspect');
        Route::get('/{receivingInspection}', [ReceivingInspectionController::class, 'show'])->middleware('permission:qc.view');
        Route::post('/{receivingInspection}/record-result', [ReceivingInspectionController::class, 'recordResult'])->middleware('permission:qc.inspect');
        Route::post('/{receivingInspection}/approve', [ReceivingInspectionController::class, 'approve'])->middleware('permission:qc.approve');
        Route::put('/{receivingInspection}/disposition', [ReceivingInspectionController::class, 'updateDisposition'])->middleware('permission:qc.edit');
        Route::post('/{receivingInspection}/transfer-to-qc', [ReceivingInspectionController::class, 'transferToQcZone'])->middleware('permission:qc.edit');
    });

    // Non-Conformance Reports (NCR) routes
    Route::prefix('ncrs')->group(function () {
        Route::get('/', [NonConformanceReportController::class, 'index'])->middleware('permission:qc.view');
        Route::post('/', [NonConformanceReportController::class, 'store'])->middleware('permission:qc.create');
        Route::get('/statistics', [NonConformanceReportController::class, 'statistics'])->middleware('permission:qc.view');
        Route::get('/statuses', [NonConformanceReportController::class, 'statuses'])->middleware('permission:qc.view');
        Route::get('/severities', [NonConformanceReportController::class, 'severities'])->middleware('permission:qc.view');
        Route::get('/defect-types', [NonConformanceReportController::class, 'defectTypes'])->middleware('permission:qc.view');
        Route::get('/dispositions', [NonConformanceReportController::class, 'dispositions'])->middleware('permission:qc.view');
        Route::get('/supplier/{supplierId}/summary', [NonConformanceReportController::class, 'supplierSummary'])->middleware('permission:qc.view');
        Route::post('/from-inspection/{receivingInspection}', [NonConformanceReportController::class, 'createFromInspection'])->middleware('permission:qc.create');
        Route::get('/{nonConformanceReport}', [NonConformanceReportController::class, 'show'])->middleware('permission:qc.view');
        Route::put('/{nonConformanceReport}', [NonConformanceReportController::class, 'update'])->middleware('permission:qc.edit');
        Route::delete('/{nonConformanceReport}', [NonConformanceReportController::class, 'destroy'])->middleware('permission:qc.delete');

        // NCR Workflow actions
        Route::post('/{nonConformanceReport}/submit-review', [NonConformanceReportController::class, 'submitForReview'])->middleware('permission:qc.edit');
        Route::post('/{nonConformanceReport}/complete-review', [NonConformanceReportController::class, 'completeReview'])->middleware('permission:qc.review');
        Route::post('/{nonConformanceReport}/set-disposition', [NonConformanceReportController::class, 'setDisposition'])->middleware('permission:qc.approve');
        Route::post('/{nonConformanceReport}/start-progress', [NonConformanceReportController::class, 'startProgress'])->middleware('permission:qc.edit');
        Route::post('/{nonConformanceReport}/close', [NonConformanceReportController::class, 'close'])->middleware('permission:qc.approve');
        Route::post('/{nonConformanceReport}/cancel', [NonConformanceReportController::class, 'cancel'])->middleware('permission:qc.edit');
    });

    }); // End of procurement module

    // ===================================================
    // MANUFACTURING MODULE (Phase 5)
    // Requires: MODULE_MANUFACTURING_ENABLED=true
    // ===================================================
    Route::middleware('module:manufacturing')->group(function () {

    // Work Center routes
    Route::prefix('work-centers')->group(function () {
        Route::get('/', [WorkCenterController::class, 'index'])->middleware('permission:manufacturing.view');
        Route::get('/list', [WorkCenterController::class, 'list'])->middleware('permission:manufacturing.view');
        Route::get('/types', [WorkCenterController::class, 'types'])->middleware('permission:manufacturing.view');
        Route::post('/', [WorkCenterController::class, 'store'])->middleware('permission:manufacturing.create');
        Route::get('/{workCenter}', [WorkCenterController::class, 'show'])->middleware('permission:manufacturing.view');
        Route::put('/{workCenter}', [WorkCenterController::class, 'update'])->middleware('permission:manufacturing.edit');
        Route::delete('/{workCenter}', [WorkCenterController::class, 'destroy'])->middleware('permission:manufacturing.delete');
        Route::post('/{workCenter}/toggle-active', [WorkCenterController::class, 'toggleActive'])->middleware('permission:manufacturing.edit');
        Route::get('/{workCenter}/availability', [WorkCenterController::class, 'availability'])->middleware('permission:manufacturing.view');
    });

    // BOM (Bill of Materials) routes
    Route::prefix('boms')->group(function () {
        Route::get('/', [BomController::class, 'index'])->middleware('permission:manufacturing.view');
        Route::get('/list', [BomController::class, 'list'])->middleware('permission:manufacturing.view');
        Route::get('/types', [BomController::class, 'types'])->middleware('permission:manufacturing.view');
        Route::get('/statuses', [BomController::class, 'statuses'])->middleware('permission:manufacturing.view');
        Route::get('/for-product/{productId}', [BomController::class, 'forProduct'])->middleware('permission:manufacturing.view');
        Route::post('/', [BomController::class, 'store'])->middleware('permission:manufacturing.create');
        Route::get('/{bom}', [BomController::class, 'show'])->middleware('permission:manufacturing.view');
        Route::put('/{bom}', [BomController::class, 'update'])->middleware('permission:manufacturing.edit');
        Route::delete('/{bom}', [BomController::class, 'destroy'])->middleware('permission:manufacturing.delete');

        // BOM Item management
        Route::post('/{bom}/items', [BomController::class, 'addItem'])->middleware('permission:manufacturing.edit');
        Route::put('/{bom}/items/{itemId}', [BomController::class, 'updateItem'])->middleware('permission:manufacturing.edit');
        Route::delete('/{bom}/items/{itemId}', [BomController::class, 'removeItem'])->middleware('permission:manufacturing.edit');

        // BOM Workflow actions
        Route::post('/{bom}/activate', [BomController::class, 'activate'])->middleware('permission:manufacturing.edit');
        Route::post('/{bom}/obsolete', [BomController::class, 'obsolete'])->middleware('permission:manufacturing.edit');
        Route::post('/{bom}/set-default', [BomController::class, 'setDefault'])->middleware('permission:manufacturing.edit');
        Route::post('/{bom}/copy', [BomController::class, 'copy'])->middleware('permission:manufacturing.create');
        Route::get('/{bom}/explode', [BomController::class, 'explode'])->middleware('permission:manufacturing.view');
    });

    // Routing routes
    Route::prefix('routings')->group(function () {
        Route::get('/', [RoutingController::class, 'index'])->middleware('permission:manufacturing.view');
        Route::get('/list', [RoutingController::class, 'list'])->middleware('permission:manufacturing.view');
        Route::get('/statuses', [RoutingController::class, 'statuses'])->middleware('permission:manufacturing.view');
        Route::get('/for-product/{productId}', [RoutingController::class, 'forProduct'])->middleware('permission:manufacturing.view');
        Route::post('/', [RoutingController::class, 'store'])->middleware('permission:manufacturing.create');
        Route::get('/{routing}', [RoutingController::class, 'show'])->middleware('permission:manufacturing.view');
        Route::put('/{routing}', [RoutingController::class, 'update'])->middleware('permission:manufacturing.edit');
        Route::delete('/{routing}', [RoutingController::class, 'destroy'])->middleware('permission:manufacturing.delete');

        // Routing Operation management
        Route::post('/{routing}/operations', [RoutingController::class, 'addOperation'])->middleware('permission:manufacturing.edit');
        Route::put('/{routing}/operations/{operationId}', [RoutingController::class, 'updateOperation'])->middleware('permission:manufacturing.edit');
        Route::delete('/{routing}/operations/{operationId}', [RoutingController::class, 'removeOperation'])->middleware('permission:manufacturing.edit');
        Route::post('/{routing}/operations/reorder', [RoutingController::class, 'reorderOperations'])->middleware('permission:manufacturing.edit');

        // Routing Workflow actions
        Route::post('/{routing}/activate', [RoutingController::class, 'activate'])->middleware('permission:manufacturing.edit');
        Route::post('/{routing}/obsolete', [RoutingController::class, 'obsolete'])->middleware('permission:manufacturing.edit');
        Route::post('/{routing}/set-default', [RoutingController::class, 'setDefault'])->middleware('permission:manufacturing.edit');
        Route::post('/{routing}/copy', [RoutingController::class, 'copy'])->middleware('permission:manufacturing.create');
        Route::post('/{routing}/calculate-lead-time', [RoutingController::class, 'calculateLeadTime'])->middleware('permission:manufacturing.view');
    });

    // Work Order routes
    Route::prefix('work-orders')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->middleware('permission:manufacturing.view');
        Route::get('/statistics', [WorkOrderController::class, 'statistics'])->middleware('permission:manufacturing.view');
        Route::get('/statuses', [WorkOrderController::class, 'statuses'])->middleware('permission:manufacturing.view');
        Route::get('/priorities', [WorkOrderController::class, 'priorities'])->middleware('permission:manufacturing.view');
        Route::post('/', [WorkOrderController::class, 'store'])->middleware('permission:manufacturing.create');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->middleware('permission:manufacturing.view');
        Route::put('/{workOrder}', [WorkOrderController::class, 'update'])->middleware('permission:manufacturing.edit');
        Route::delete('/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('permission:manufacturing.delete');

        // Work Order Workflow actions
        Route::post('/{workOrder}/release', [WorkOrderController::class, 'release'])->middleware('permission:manufacturing.release');
        Route::post('/{workOrder}/start', [WorkOrderController::class, 'start'])->middleware('permission:manufacturing.edit');
        Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete'])->middleware('permission:manufacturing.complete');
        Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->middleware('permission:manufacturing.edit');
        Route::post('/{workOrder}/hold', [WorkOrderController::class, 'hold'])->middleware('permission:manufacturing.edit');
        Route::post('/{workOrder}/resume', [WorkOrderController::class, 'resume'])->middleware('permission:manufacturing.edit');

        // Work Order Operation management
        Route::post('/{workOrder}/operations/{operationId}/start', [WorkOrderController::class, 'startOperation'])->middleware('permission:manufacturing.edit');
        Route::post('/{workOrder}/operations/{operationId}/complete', [WorkOrderController::class, 'completeOperation'])->middleware('permission:manufacturing.complete');

        // Material and Finished Goods
        Route::get('/{workOrder}/material-requirements', [WorkOrderController::class, 'materialRequirements'])->middleware('permission:manufacturing.view');
        Route::post('/{workOrder}/issue-materials', [WorkOrderController::class, 'issueMaterials'])->middleware('permission:manufacturing.edit');
        Route::post('/{workOrder}/receive-finished-goods', [WorkOrderController::class, 'receiveFinishedGoods'])->middleware('permission:manufacturing.complete');
    });

    }); // End of manufacturing module
});
