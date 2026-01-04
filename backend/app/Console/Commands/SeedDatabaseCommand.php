<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-fresh
                            {--demo : Include demo/sample data}
                            {--minimal : Run in minimal mode (default)}
                            {--force : Force the operation when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations with optional demo data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = $this->option('demo') ? 'demo' : 'minimal';

        $this->components->info("Running database fresh with seeding in {$mode} mode...");

        // Set the environment variable for seeder mode
        putenv("SEED_MODE={$mode}");
        $_ENV['SEED_MODE'] = $mode;

        // Build migrate:fresh command options
        $options = ['--seed' => true];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        // Run migrate:fresh with seed
        $exitCode = Artisan::call('migrate:fresh', $options, $this->output);

        if ($exitCode === 0) {
            $this->newLine();
            $this->components->info('Database seeded successfully!');

            if ($mode === 'minimal') {
                $this->components->info('Minimal mode: Only system essentials were seeded.');
                $this->components->info('To include demo data, use: php artisan db:seed-fresh --demo');
            } else {
                $this->components->info('Demo mode: All sample data has been seeded.');
            }
        }

        return $exitCode;
    }
}
