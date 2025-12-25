<?php

use App\Exceptions\BusinessException;
use App\Exceptions\ModuleDisabledException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'module' => \App\Http\Middleware\CheckModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Model not found (404)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();
                $message = $previous instanceof ModelNotFoundException
                    ? class_basename($previous->getModel()) . ' not found'
                    : 'Resource not found';

                return response()->json([
                    'message' => $message,
                    'error' => 'not_found'
                ], 404);
            }
        });

        // Unauthenticated (401)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'unauthenticated'
                ], 401);
            }
        });

        // Forbidden (403)
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied',
                    'error' => 'forbidden'
                ], 403);
            }
        });

        // Validation error (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'error' => 'validation_error',
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // Business logic errors (custom status code, default 422)
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'business_error'
                ], $e->getStatusCode());
            }
        });

        // Module disabled errors (403)
        $exceptions->render(function (ModuleDisabledException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json($e->getErrorDetails(), $e->getStatusCode());
            }
        });
    })
    ->create();
