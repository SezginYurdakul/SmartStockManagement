<?php

namespace App\Http\Middleware;

use App\Exceptions\ModuleDisabledException;
use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    public function __construct(
        private readonly ModuleService $moduleService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $module  The module to check (e.g., 'procurement', 'manufacturing')
     * @param  string|null  $feature  Optional specific feature to check within the module
     * @throws ModuleDisabledException
     */
    public function handle(Request $request, Closure $next, string $module, ?string $feature = null): Response
    {
        // Check if the module is enabled
        if (!$this->moduleService->isModuleEnabled($module)) {
            throw new ModuleDisabledException(
                "The '{$module}' module is not enabled.",
                $module
            );
        }

        // If a specific feature is specified, check that too
        if ($feature !== null && !$this->moduleService->isFeatureEnabled($module, $feature)) {
            throw new ModuleDisabledException(
                "The '{$feature}' feature in '{$module}' module is not enabled.",
                $module,
                $feature
            );
        }

        return $next($request);
    }
}
