<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request; every tenant-aware query reads from it.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch N+1 queries, missing attributes and bad mass-assignment early
        // in development; stay lenient in production so a mistake degrades
        // rather than 500s for every user.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Never guard-off in production by accident.
        Model::preventLazyLoading(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
