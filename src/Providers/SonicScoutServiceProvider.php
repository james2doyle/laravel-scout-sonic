<?php

namespace james2doyle\SonicScout\Providers;

use Illuminate\Support\ServiceProvider;
use james2doyle\SonicScout\Engines\SonicSearchEngine;
use Laravel\Scout\EngineManager;
use SonicSearch\ChannelFactory;

class SonicScoutServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app->make(EngineManager::class)->extend('sonic', function () {
            return new SonicSearchEngine(
                \config('scout.sonic.address'),
                \config('scout.sonic.port'),
                \config('scout.sonic.password'),
                \config('scout.sonic.connection_timeout'));
        });
    }
}
