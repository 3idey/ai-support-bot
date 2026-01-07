<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AiServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('textExtractor', function () {
            return new \App\Services\TextExtractor();
        });

        $this->app->bind('chunker', function () {
            return new \App\Services\Chunker();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
