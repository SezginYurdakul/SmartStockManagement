<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the audit logging system.
    | Audit logging tracks all changes to models for compliance and debugging.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Sync Logging
    |--------------------------------------------------------------------------
    |
    | When set to true, audit logs are written synchronously (within the same
    | transaction). This is more reliable but slower.
    |
    | When set to false, audit logs are written asynchronously via queue.
    | This is faster but less reliable (queue failures can cause log loss).
    |
    | Recommended: false for production (use queue), true for development/test
    |
    */

    'sync' => env('AUDIT_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for async audit logging.
    |
    */

    'queue' => env('AUDIT_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    |
    | How many days to keep audit logs before cleanup.
    | Default: 2555 days (7 years) for compliance requirements.
    |
    */

    'retention_days' => env('AUDIT_RETENTION_DAYS', 2555),

    /*
    |--------------------------------------------------------------------------
    | Critical Events
    |--------------------------------------------------------------------------
    |
    | Events that should always be logged synchronously, even if sync=false.
    | These are critical events that must not be lost.
    |
    */

    'critical_events' => [
        'deleted',
        'approved',
        'rejected',
        'stock_adjusted',
        'quality_hold_placed',
        'quality_hold_released',
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical Entities
    |--------------------------------------------------------------------------
    |
    | Entity types that should always be logged synchronously, even if sync=false.
    | These are critical models that must not lose audit logs.
    |
    */

    'critical_entities' => [
        \App\Models\WorkOrder::class,
        \App\Models\PurchaseOrder::class,
        \App\Models\StockMovement::class,
        \App\Models\SalesOrder::class,
        \App\Models\GoodsReceivedNote::class,
        \App\Models\DeliveryNote::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should never be logged in audit changes.
    | These are typically timestamps or system fields.
    |
    */

    'excluded_fields' => [
        'updated_at',
        'created_at',
        'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be masked in audit logs (shown as ***MASKED***).
    | These are sensitive data like passwords, API keys, etc.
    |
    */

    'sensitive_fields' => [
        'password',
        'api_key',
        'secret',
        'token',
        'remember_token',
    ],
];
