<?php

namespace Database\Seeders\Traits;

trait SeederModeTrait
{
    /**
     * Check if running in demo mode (with sample data)
     */
    protected function isDemoMode(): bool
    {
        // Check command line option first: php artisan db:seed --demo
        // Then check environment variable: SEED_MODE=demo
        // Default: minimal (no demo data)

        if (app()->runningInConsole()) {
            // Check if --demo flag was passed
            $argv = $_SERVER['argv'] ?? [];
            if (in_array('--demo', $argv)) {
                return true;
            }
        }

        return env('SEED_MODE', 'minimal') === 'demo';
    }

    /**
     * Check if running in minimal mode (system essentials only)
     */
    protected function isMinimalMode(): bool
    {
        return !$this->isDemoMode();
    }

    /**
     * Output mode information
     */
    protected function outputModeInfo(): void
    {
        $mode = $this->isDemoMode() ? 'DEMO' : 'MINIMAL';
        $this->command?->info("Running in {$mode} mode");
    }
}
