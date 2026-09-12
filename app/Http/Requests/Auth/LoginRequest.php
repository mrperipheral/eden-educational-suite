<?php

namespace App\Http\Requests\Auth;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Order matters: rate-limit first, then verify the password, then enforce
     * account status. A suspended user still has to prove the password before
     * they learn their account is blocked, so this does not leak account state.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->canAuthenticate()) {
            $message = $user->status->authenticationBlockedMessage();

            // Recorded explicitly (distinct from the generic
            // `auth.login.success` the `Login` event above already fired) —
            // the account status is the security-relevant fact here, not
            // just that a session briefly existed before being torn down.
            app(AuditRecorder::class)->record(
                event: 'auth.login.blocked',
                summary: __(':name attempted to sign in while :status.', ['name' => $user->name, 'status' => $user->status->label()]),
                actor: $user,
            );

            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Per-identity + per-IP throttle key. Lower-cased so casing cannot be used
     * to sidestep the limit.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
