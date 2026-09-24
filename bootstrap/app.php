<?php

use App\Http\Middleware\EnforceTenant;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render (and most PaaS hosts) terminate TLS at their own edge and
        // forward requests to this container over plain HTTP, setting
        // X-Forwarded-* headers. Without trusting them, Laravel thinks every
        // request is HTTP, which breaks signed URLs (email verification,
        // password reset) generated with a forced https:// scheme. The
        // container has no other inbound path, so trusting "whichever proxy
        // is in front of us" is safe here.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'tenant' => EnforceTenant::class,
            'module' => EnsureModuleEnabled::class,
        ]);

        // M28: applies to every response (including error pages), not just
        // authenticated/tenant routes.
        $middleware->append(SecurityHeaders::class);

        $middleware->redirectTo(
            guests: '/login',
            users: '/dashboard',
        );

        // Paystack's webhook (M20) is a server-to-server POST with no
        // session/token — it is authenticated by signature verification in
        // the controller instead (see PaystackWebhookController).
        $middleware->validateCsrfTokens(except: [
            'webhooks/paystack',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
