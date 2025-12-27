<?php

/**
 * Module Configuration
 *
 * SmartStockManagement uses a modular MRP II architecture:
 * - Core: Always enabled (stock tracking, products, warehouses)
 * - Procurement: Optional (suppliers, purchase orders, receiving, QC)
 * - Manufacturing: Optional (BOM, work orders, production, QC)
 * - Integrations: External services (prediction service, webhooks)
 *
 * Modules are logical separations controlled by feature flags,
 * not physical folder restructuring.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Core Module
    |--------------------------------------------------------------------------
    |
    | The core module is always enabled and cannot be disabled.
    | It provides fundamental stock management functionality.
    |
    */
    'core' => [
        'enabled' => true, // Always enabled, cannot be disabled
        'name' => 'Core Stock Management',
        'description' => 'Fundamental stock tracking, products, categories, warehouses, and attributes',
        'features' => [
            'stock_tracking' => true,
            'multi_warehouse' => true,
            'lot_tracking' => true,
            'serial_tracking' => true,
            'stock_reservations' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Procurement Module
    |--------------------------------------------------------------------------
    |
    | Handles supplier management, purchase orders, and receiving operations.
    | Includes optional basic quality control (pass/fail inspections).
    |
    */
    'procurement' => [
        'enabled' => env('MODULE_PROCUREMENT_ENABLED', true),
        'name' => 'Procurement',
        'description' => 'Supplier management, purchase orders, receiving, and quality control',
        'features' => [
            'suppliers' => true,
            'purchase_orders' => true,
            'receiving' => true,
            'quality_control' => env('MODULE_PROCUREMENT_QC_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manufacturing Module
    |--------------------------------------------------------------------------
    |
    | Handles bill of materials, work orders, and production operations.
    | Includes optional basic quality control (pass/fail inspections).
    |
    */
    'manufacturing' => [
        'enabled' => env('MODULE_MANUFACTURING_ENABLED', false),
        'name' => 'Manufacturing',
        'description' => 'Bill of materials, work orders, production, and quality control',
        'features' => [
            'bom' => true,
            'work_orders' => true,
            'production' => true,
            'quality_control' => env('MODULE_MANUFACTURING_QC_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | External service integrations including Python prediction service
    | and webhook system for external Sales/Finance systems.
    |
    */
    'integrations' => [
        /*
        |--------------------------------------------------------------------------
        | Prediction Service
        |--------------------------------------------------------------------------
        |
        | Python-based prediction service for demand forecasting,
        | reorder point optimization, and safety stock calculations.
        | Stateless service that queries Laravel API for data.
        |
        | Communication: Sync HTTP (Phase 1), Async Redis Queue (Future)
        |
        */
        'prediction_service' => [
            'enabled' => env('PREDICTION_SERVICE_ENABLED', false),
            'base_url' => env('PREDICTION_SERVICE_URL', 'http://localhost:8001'),
            'api_key' => env('PREDICTION_SERVICE_API_KEY'),
            'timeout' => env('PREDICTION_SERVICE_TIMEOUT', 5000), // milliseconds
            'retry_attempts' => 3,
            'retry_delay' => 100, // milliseconds
        ],

        /*
        |--------------------------------------------------------------------------
        | Webhooks
        |--------------------------------------------------------------------------
        |
        | Webhook system for notifying external systems (Sales, Finance, etc.)
        | about stock events like reservations, issues, and low stock alerts.
        |
        */
        'webhooks' => [
            'enabled' => env('WEBHOOKS_ENABLED', false),
            'signature_algorithm' => 'sha256',
            'timeout' => env('WEBHOOK_TIMEOUT', 30), // seconds
            'retry_attempts' => 3,
            'retry_delay' => 60, // seconds
        ],

        /*
        |--------------------------------------------------------------------------
        | External Stock Reservation API
        |--------------------------------------------------------------------------
        |
        | Allows external systems (Sales platforms, etc.) to check availability,
        | reserve stock, and confirm/release reservations.
        |
        */
        'external_reservations' => [
            'enabled' => env('EXTERNAL_RESERVATIONS_ENABLED', false),
            'default_expiry_hours' => 24,
            'max_expiry_hours' => 168, // 7 days
            'require_api_key' => true,
        ],
    ],
];
