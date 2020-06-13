<?php

namespace james2doyle\SonicScout\Providers;

use Illuminate\Support\ServiceProvider;
use james2doyle\SonicScout\Engines\SonicSearchEngine;
use Laravel\Scout\EngineManager;
use Psonic\Client;
use Psonic\Control;
use Psonic\Ingest;
use Psonic\Search;

class SonicScoutServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app->make(EngineManager::class)->extend('sonic', function () {
            return new SonicSearchEngine(
                new Ingest($this->generateClient()),
                new Search($this->generateClient()),
                new Control($this->generateClient()),
                \config('scout.sonic.password')
            );
        });
    }

    private function generateClient(): Client {
        return new Client(
            \config('scout.sonic.address'),
            \config('scout.sonic.port'),
            \config('scout.sonic.connection_timeout')
        );
    }
}
