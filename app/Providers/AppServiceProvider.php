<?php

namespace App\Providers;

use App\Services\Interfaces\LoggingServiceInterface;
use App\Services\LoggingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar el servicio de logging
        $this->app->singleton(LoggingServiceInterface::class, LoggingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
