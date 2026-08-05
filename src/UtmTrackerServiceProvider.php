<?php

namespace Fsuuaas\UtmTracker;

use Fsuuaas\UtmTracker\Actions\RecordUtm;
use Fsuuaas\UtmTracker\Contracts\RecordsUtm;
use Fsuuaas\UtmTracker\Fields\FieldRegistry;
use Illuminate\Support\ServiceProvider;

class UtmTrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/utm-tracker.php', 'utm-tracker');

        // The registry is config-like and safe to share for the process lifetime;
        // per-request state lives on the Request, not here.
        $this->app->singleton(UtmTracker::class, fn () => new UtmTracker(FieldRegistry::default()));

        $this->app->bind(RecordsUtm::class, RecordUtm::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'utm-tracker');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/utm-tracker.php' => config_path('utm-tracker.php'),
        ], 'utm-tracker-config');

        // Fresh installs only. An app that already has a utm_records table should
        // publish utm-tracker-indexes instead.
        $this->publishes([
            __DIR__.'/../database/migrations/create_utm_records_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His').'_create_utm_records_table.php'
            ),
        ], 'utm-tracker-migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/add_utm_records_indexes.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His', time() + 1).'_add_utm_records_indexes.php'
            ),
        ], 'utm-tracker-indexes');

        $this->publishes([
            __DIR__.'/../resources/js/utm-capture.js' => public_path('vendor/utm-tracker/utm-capture.js'),
        ], 'utm-tracker-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/utm-tracker'),
        ], 'utm-tracker-views');
    }
}
