<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                "message" => "Unauthenticated",
            ], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                "message" => "Forbidden. Required roles: " . implode(", ", $roles),
                "required_roles" => $roles,
            ], 403);
        }

        return $next($request);
    }
}
