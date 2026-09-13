<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-level security headers (M28 — Platform Security Hardening).
 * This app has no first-party `<iframe>`/embedding use case anywhere in the
 * Blade/Tailwind/Alpine UI, so clickjacking protection is unconditional.
 * Headers here are the ones Laravel does not set by default; they are a
 * defense-in-depth layer, not a substitute for the deployment-level
 * controls (actual HTTPS termination, a WAF, etc.) documented in
 * `docs/security-hardening.md`.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS only makes sense once the app is genuinely served over HTTPS
        // (production) — sending it over a plain local-dev HTTP connection
        // would be actively misleading about the app's real transport
        // security, so it is gated on both.
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
