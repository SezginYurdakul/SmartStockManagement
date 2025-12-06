<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json([
                "message" => "Unauthenticated",
            ], 401);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if (!$request->user()->hasPermission($permission)) {
                return response()->json([
                    "message" => "Forbidden. Missing permission: " . $permission,
                    "required_permission" => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
