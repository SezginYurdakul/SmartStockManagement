<?php

namespace App\Providers;

use App\Scout\ElasticsearchEngine;
use App\Models\Bom;
use App\Models\Product;
use App\Models\CompanyCalendar;
use App\Observers\BomObserver;
use App\Observers\ProductObserver;
use App\Observers\CompanyCalendarObserver;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Laravel\Scout\EngineManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind ImageManager with GD driver
        $this->app->singleton(ImageManager::class, function () {
            return new ImageManager(new Driver());
        });

        // Bind Elasticsearch Client
        $this->app->singleton(Client::class, function () {
            $host = config('elasticsearch.host', 'http://elasticsearch:9200');

            $builder = ClientBuilder::create()
                ->setHosts([$host]);

            // Add authentication if credentials are configured
            $user = config('elasticsearch.user');
            $password = config('elasticsearch.password');

            if ($user && $password) {
                $builder->setBasicAuthentication($user, $password);
            }

            // Add API key authentication if configured
            $apiKey = config('elasticsearch.api_key');
            if ($apiKey) {
                $builder->setApiKey($apiKey);
            }

            // Configure SSL verification
            if (config('elasticsearch.ssl_verification') === false) {
                $builder->setSSLVerification(false);
            }

            return $builder->build();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom Elasticsearch Scout engine
        resolve(EngineManager::class)->extend('elasticsearch', function () {
            return new ElasticsearchEngine(
                $this->app->make(Client::class)
            );
        });

        // Rate limiter for single product variant generation (10 per minute)
        RateLimiter::for('variant-generate', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for bulk variant generation (5 per minute - heavy operation)
        RateLimiter::for('bulk-variant-generate', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Register Observers for automatic MRP cache invalidation
        Bom::observe(BomObserver::class);
        \App\Models\BomItem::observe(\App\Observers\BomItemObserver::class);
        Product::observe(ProductObserver::class);
        CompanyCalendar::observe(CompanyCalendarObserver::class);
    }
}
