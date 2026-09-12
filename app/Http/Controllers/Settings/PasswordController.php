<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated()['password']),
        ]);

        // No tenant context on this route (see `docs/audit.md`) — school_id
        // is recorded as null, same as every other account-level security event.
        $this->audit->record(
            event: 'auth.password.changed',
            summary: __(':name changed their password.', ['name' => $user->name]),
            actor: $user,
        );

        return back()->with('status', __('Your password has been changed.'));
    }
}
