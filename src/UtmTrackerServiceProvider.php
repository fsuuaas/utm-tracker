<?php

namespace Fsuuaas\UtmTracker;

use Illuminate\Support\ServiceProvider;

class UtmTrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/utm-tracker.php', 'utm-tracker');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'utm-tracker');

        $this->publishes([
            __DIR__.'/../config/utm-tracker.php' => config_path('utm-tracker.php'),
        ], 'utm-tracker-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_utm_records_table.php.stub' =>
                database_path('migrations/'.date('Y_m_d_His', time()).'_create_utm_records_table.php'),
        ], 'utm-tracker-migrations');

        $this->publishes([
            __DIR__.'/../resources/js/utm-capture.js' => public_path('vendor/utm-tracker/utm-capture.js'),
        ], 'utm-tracker-assets');
    }
}
