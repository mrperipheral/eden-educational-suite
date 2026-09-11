<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationThread;
use Illuminate\Http\RedirectResponse;

/**
 * Lifecycle transitions for a Communication Hub thread — `resolve` /
 * `escalate` / `reopen`. Each is its own gate (`communication.resolve` /
 * `.escalate` / `.manage`), mirroring the attendance register's dedicated
 * submit/reopen endpoints. `{thread}` is resolved by tenant-scoped
 * `findOrFail`, so another school's id 404s.
 */
class CommunicationStatusController extends Controller
{
    public function resolve(int $thread): RedirectResponse
    {
        $this->authorize('communication.resolve');

        CommunicationThread::query()->findOrFail($thread)->resolve();

        return back()->with('status', __('Marked resolved.'));
    }

    public function escalate(int $thread): RedirectResponse
    {
        $this->authorize('communication.escalate');

        CommunicationThread::query()->findOrFail($thread)->escalate();

        return back()->with('status', __('Escalated.'));
    }

    public function reopen(int $thread): RedirectResponse
    {
        $this->authorize('communication.resolve');

        CommunicationThread::query()->findOrFail($thread)->reopen();

        return back()->with('status', __('Reopened.'));
    }
}
