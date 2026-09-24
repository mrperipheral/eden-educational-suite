<?php

namespace App\Providers;

use App\Enums\Module;
use App\Support\Modules\SchoolModules;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request; every tenant-aware query reads from it.
        $this->app->scoped(TenantContext::class);

        // One module-activation resolver per request; the school's module
        // overrides are read from the database once and memoised.
        $this->app->scoped(SchoolModules::class);
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

            // Session/CSRF cookies must never travel over plain HTTP in prod,
            // regardless of what the environment file says.
            config(['session.secure' => true]);
        }

        // `@module('attendance') … @endmodule` — is a feature module switched on
        // for the current school? A convenience for views (nav, dashboards);
        // it is not an authorization check.
        Blade::if('module', function (string $module): bool {
            $case = Module::tryFrom($module);

            return $case !== null && app(SchoolModules::class)->enabled($case);
        });

        // Single definition of "an acceptable password", used by every
        // registration / reset / change form via Password::defaults().
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->min(10)->mixedCase()->uncompromised()
                : $rule;
        });

        // M28: named rate limiters for the two classes of route the security
        // review flagged as expensive/sensitive and unthrottled — CSV/report
        // exports (potentially wide, unbounded-by-page queries) and Paystack
        // payment initiation (an external API call + a new DB row per hit).
        // Keyed by user id — every route these guard sits behind `auth`
        // already, so there is always one.
        RateLimiter::for('exports', fn ($request) => Limit::perMinute(20)->by($request->user()?->getAuthIdentifier()));

        RateLimiter::for('payment-initiation', fn ($request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier()));

        // Brevo over its HTTP API rather than SMTP — Render's free tier
        // blocks outbound traffic on the SMTP ports (25, 465, 587).
        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', config('services.brevo.key')),
            );
        });
    }
}
