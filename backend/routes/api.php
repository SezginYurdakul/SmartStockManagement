<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
| Routes are organized into modular files for better maintainability:
| - auth.php: Authentication routes (login, register, logout, etc.)
| - core.php: Core system routes (users, roles, settings, currencies)
| - products.php: Product management (products, categories, attributes, variants)
| - inventory.php: Inventory management (warehouses, stock, movements)
| - procurement.php: Procurement module (suppliers, POs, GRNs)
| - qc.php: Quality Control module (acceptance rules, inspections, NCRs)
| - manufacturing.php: Manufacturing module (work centers, BOMs, routings, work orders, MRP, CRP)
| - sales.php: Sales module (customer groups, customers, sales orders, delivery notes)
|
*/

// Health check endpoint
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

// ============================================================================
// AUTHENTICATION ROUTES (Public + Protected)
// ============================================================================
require __DIR__ . '/api/auth.php';

// ============================================================================
// PROTECTED ROUTES (Require Authentication)
// ============================================================================
Route::middleware('auth:sanctum')->group(function () {

    // Core system routes (users, roles, permissions, settings, currencies, UoM)
    require __DIR__ . '/api/core.php';

    // Product management routes (products, categories, attributes, variants, UOM conversions)
    require __DIR__ . '/api/products.php';

    // Inventory management routes (warehouses, stock, movements)
    require __DIR__ . '/api/inventory.php';

    // ========================================================================
    // MODULE-BASED ROUTES
    // ========================================================================

    // Procurement Module (suppliers, purchase orders, goods received notes)
    // Requires: MODULE_PROCUREMENT_ENABLED=true
    Route::middleware('module:procurement')->group(function () {
        require __DIR__ . '/api/procurement.php';
    });

    // Quality Control Module (acceptance rules, inspections, NCRs)
    // Requires: MODULE_QC_ENABLED=true
    Route::middleware('module:qc')->group(function () {
        require __DIR__ . '/api/qc.php';
    });

    // Manufacturing Module (work centers, BOMs, routings, work orders, MRP, CRP)
    // Requires: MODULE_MANUFACTURING_ENABLED=true
    Route::middleware('module:manufacturing')->group(function () {
        require __DIR__ . '/api/manufacturing.php';
    });

    // Sales Module (customer groups, customers, sales orders, delivery notes)
    // Requires: MODULE_SALES_ENABLED=true
    Route::middleware('module:sales')->group(function () {
        require __DIR__ . '/api/sales.php';
    });

});
