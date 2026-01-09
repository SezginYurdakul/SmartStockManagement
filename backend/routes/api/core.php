<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\OverDeliveryToleranceController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\CompanyCalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core System Routes
|--------------------------------------------------------------------------
| Users, Roles, Permissions, Settings, Currencies, UoM
*/

// User Management
Route::prefix('users')->group(function () {
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
    });

    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('/{id}/restore', [UserController::class, 'restore'])->middleware('permission:users.delete');
    Route::delete('/{id}/force', [UserController::class, 'forceDelete'])->middleware('role:admin');
});

// User Invitations
Route::prefix('invitations')->group(function () {
    // Public routes (no auth required)
    Route::withoutMiddleware('auth:sanctum')->group(function () {
        // Support both path parameter and query parameter for flexibility
        Route::get('/accept/{token?}', [InvitationController::class, 'show']);
        Route::post('/accept/{token?}', [InvitationController::class, 'accept']);
    });

    // Protected routes (require authentication)
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/', [InvitationController::class, 'index']);
    });

    Route::post('/', [InvitationController::class, 'store'])->middleware('permission:users.create');
    Route::post('/{id}/resend', [InvitationController::class, 'resend']); // Permission check in service (allows inviter)
    Route::delete('/{id}', [InvitationController::class, 'destroy'])->middleware('permission:users.delete');
});

// Role & Permission Management (Admin only)
Route::middleware('role:admin')->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{role}/permissions/assign', [RoleController::class, 'assignPermissions']);
    Route::post('roles/{role}/permissions/revoke', [RoleController::class, 'revokePermissions']);

    Route::apiResource('permissions', PermissionController::class);
    Route::get('permissions/modules/list', [PermissionController::class, 'modules']);
});

// Settings
Route::prefix('settings')->group(function () {
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::get('/groups', [SettingController::class, 'groups']);
        Route::get('/group/{group}', [SettingController::class, 'group']);
        Route::get('/{group}/{key}', [SettingController::class, 'show']);
    });

    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/', [SettingController::class, 'store']);
        Route::put('/{group}/{key}', [SettingController::class, 'update']);
        Route::delete('/{group}/{key}', [SettingController::class, 'destroy']);
    });
});

// Over-Delivery Tolerance (Company-specific, Admin only)
Route::prefix('over-delivery-tolerance')->group(function () {
    Route::middleware(['permission:settings.view', 'role:admin'])->group(function () {
        Route::get('/', [OverDeliveryToleranceController::class, 'show']);
        Route::get('/levels', [OverDeliveryToleranceController::class, 'levels']);
    });

    Route::middleware(['permission:settings.edit', 'role:admin'])->group(function () {
        Route::put('/', [OverDeliveryToleranceController::class, 'update']);
    });
});

// Company Calendar (for MRP working days)
Route::prefix('company-calendar')->group(function () {
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/', [CompanyCalendarController::class, 'index']);
        Route::get('/date-range', [CompanyCalendarController::class, 'getDateRange']);
        Route::get('/{calendar}', [CompanyCalendarController::class, 'show']);
    });

    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/', [CompanyCalendarController::class, 'store']);
        Route::post('/bulk', [CompanyCalendarController::class, 'bulkStore']);
        Route::put('/{calendar}', [CompanyCalendarController::class, 'update']);
        Route::delete('/{calendar}', [CompanyCalendarController::class, 'destroy']);
    });
});

// Currencies
Route::prefix('currencies')->group(function () {
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/', [CurrencyController::class, 'index']);
        Route::get('/active', [CurrencyController::class, 'active']);
        Route::get('/{currency}', [CurrencyController::class, 'show']);
        Route::get('/exchange-rate/get', [CurrencyController::class, 'getExchangeRate']);
        Route::get('/exchange-rate/history', [CurrencyController::class, 'exchangeRateHistory']);
        Route::post('/convert', [CurrencyController::class, 'convert']);
    });

    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/', [CurrencyController::class, 'store']);
        Route::put('/{currency}', [CurrencyController::class, 'update']);
        Route::delete('/{currency}', [CurrencyController::class, 'destroy']);
        Route::post('/{currency}/toggle-active', [CurrencyController::class, 'toggleActive']);
        Route::post('/exchange-rate/set', [CurrencyController::class, 'setExchangeRate']);
    });
});

// Units of Measure
Route::prefix('units-of-measure')->group(function () {
    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/', [UnitOfMeasureController::class, 'index']);
        Route::get('/list', [UnitOfMeasureController::class, 'list']);
        Route::get('/types', [UnitOfMeasureController::class, 'types']);
        Route::get('/{unitOfMeasure}', [UnitOfMeasureController::class, 'show']);
    });

    Route::middleware('permission:settings.edit')->group(function () {
        Route::post('/', [UnitOfMeasureController::class, 'store']);
        Route::put('/{unitOfMeasure}', [UnitOfMeasureController::class, 'update']);
        Route::delete('/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy']);
    });
});
