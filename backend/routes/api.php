<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return response()->json([
        "message" => "API is working",
        "version" => "1.0.0",
        "status" => "active"
    ]);
});

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
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
        Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
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
});
