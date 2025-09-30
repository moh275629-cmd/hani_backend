<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ProfanityService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfanityService::class, fn() => new ProfanityService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
