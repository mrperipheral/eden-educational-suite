<?php

namespace App\Http\Middleware;

use App\Http\Requests\Auth\LoginRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminates the session of an authenticated user whose account has been
 * suspended or disabled since they signed in.
 *
 * Login itself is guarded by {@see LoginRequest}; this
 * middleware covers the window between an admin disabling an account and the
 * user's existing session expiring. It is applied to every authenticated route
 * group so access cannot be retained by a stale cookie.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAuthenticate()) {
            $message = $user->status->authenticationBlockedMessage();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
