<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ModuleService
{
    /**
     * Cache key prefix for module status
     */
    private const CACHE_PREFIX = 'module_status_';

    /**
     * Cache TTL in seconds
     */
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if a module is enabled
     */
    public function isModuleEnabled(string $module): bool
    {
        // Core module is always enabled
        if ($module === 'core') {
            return true;
        }

        return Cache::remember(
            self::CACHE_PREFIX . $module,
            self::CACHE_TTL,
            fn () => (bool) config("modules.{$module}.enabled", false)
        );
    }

    /**
     * Check if a specific feature within a module is enabled
     */
    public function isFeatureEnabled(string $module, string $feature): bool
    {
        // First check if the module itself is enabled
        if (!$this->isModuleEnabled($module)) {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX . "{$module}_{$feature}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => (bool) config("modules.{$module}.features.{$feature}", false)
        );
    }

    /**
     * Get all enabled modules
     *
     * @return array<string, array>
     */
    public function getEnabledModules(): array
    {
        $modules = [];
        $allModules = ['core', 'procurement', 'manufacturing'];

        foreach ($allModules as $module) {
            if ($this->isModuleEnabled($module)) {
                $modules[$module] = $this->getModuleConfig($module);
            }
        }

        return $modules;
    }

    /**
     * Get configuration for a specific module
     */
    public function getModuleConfig(string $module): array
    {
        $config = config("modules.{$module}", []);

        if (empty($config)) {
            return [];
        }

        return [
            'name' => $config['name'] ?? $module,
            'description' => $config['description'] ?? '',
            'enabled' => $this->isModuleEnabled($module),
            'features' => $this->getModuleFeatures($module),
        ];
    }

    /**
     * Get all features for a module with their enabled status
     *
     * @return array<string, bool>
     */
    public function getModuleFeatures(string $module): array
    {
        $features = config("modules.{$module}.features", []);
        $result = [];

        foreach ($features as $feature => $enabled) {
            $result[$feature] = $this->isFeatureEnabled($module, $feature);
        }

        return $result;
    }

    /**
     * Get integration configuration
     */
    public function getIntegrationConfig(string $integration): array
    {
        return config("modules.integrations.{$integration}", []);
    }

    /**
     * Check if an integration is enabled
     */
    public function isIntegrationEnabled(string $integration): bool
    {
        return (bool) config("modules.integrations.{$integration}.enabled", false);
    }

    /**
     * Get module status summary for health checks or admin panel
     */
    public function getModuleStatus(): array
    {
        return [
            'modules' => $this->getEnabledModules(),
            'integrations' => [
                'prediction_service' => [
                    'enabled' => $this->isIntegrationEnabled('prediction_service'),
                    'url' => config('modules.integrations.prediction_service.base_url'),
                ],
                'webhooks' => [
                    'enabled' => $this->isIntegrationEnabled('webhooks'),
                ],
                'external_reservations' => [
                    'enabled' => $this->isIntegrationEnabled('external_reservations'),
                ],
            ],
        ];
    }

    /**
     * Clear module cache (useful after config changes)
     */
    public function clearCache(): void
    {
        $modules = ['core', 'procurement', 'manufacturing'];
        $features = [
            'stock_tracking', 'multi_warehouse', 'lot_tracking', 'serial_tracking',
            'stock_reservations', 'suppliers', 'purchase_orders', 'receiving',
            'quality_control', 'bom', 'work_orders', 'production',
        ];

        foreach ($modules as $module) {
            Cache::forget(self::CACHE_PREFIX . $module);

            foreach ($features as $feature) {
                Cache::forget(self::CACHE_PREFIX . "{$module}_{$feature}");
            }
        }
    }

    /**
     * Check if quality control is enabled for a module
     */
    public function isQualityControlEnabled(string $module): bool
    {
        return $this->isFeatureEnabled($module, 'quality_control');
    }

    /**
     * Get list of available modules (regardless of enabled status)
     *
     * @return array<string>
     */
    public function getAvailableModules(): array
    {
        return ['core', 'procurement', 'manufacturing'];
    }

    /**
     * Validate that required modules for a feature are enabled
     *
     * @param array<string> $requiredModules
     * @throws \RuntimeException
     */
    public function validateModules(array $requiredModules): void
    {
        foreach ($requiredModules as $module) {
            if (!$this->isModuleEnabled($module)) {
                throw new \RuntimeException(
                    "Required module '{$module}' is not enabled. " .
                    "Please enable it in your configuration or .env file."
                );
            }
        }
    }
}
