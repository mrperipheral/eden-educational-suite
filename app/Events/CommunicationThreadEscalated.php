<?php

namespace App\Events;

use App\Models\CommunicationThread;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired from {@see CommunicationThread::escalate()}. See
 * `App\Listeners\Notifications\SendCommunicationEscalationNotifications` and
 * `docs/communication.md`.
 */
class CommunicationThreadEscalated
{
    use Dispatchable;

    public function __construct(public readonly CommunicationThread $thread) {}
}
