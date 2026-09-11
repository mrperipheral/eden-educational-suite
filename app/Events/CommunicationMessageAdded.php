<?php

namespace App\Events;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever a new message is added to a {@see CommunicationThread}. See
 * `App\Listeners\Notifications\SendCommunicationMessageNotification` and
 * `docs/communication.md`.
 */
class CommunicationMessageAdded
{
    use Dispatchable;

    public function __construct(
        public readonly CommunicationThread $thread,
        public readonly CommunicationMessage $message,
    ) {}
}
