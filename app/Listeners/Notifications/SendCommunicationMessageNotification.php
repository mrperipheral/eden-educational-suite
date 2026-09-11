<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Events\CommunicationMessageAdded;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Str;

/**
 * Notifies a thread's assignee when someone else adds a message to it — the
 * "communication received" event named in the M18 spec. The sender is never
 * notified of their own message.
 */
class SendCommunicationMessageNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(CommunicationMessageAdded $event): void
    {
        $thread = $event->thread;
        $assignee = $thread->assignedTo;

        if ($assignee === null || $assignee->getKey() === $event->message->sender_id) {
            return;
        }

        $this->dispatcher->sendToUser(
            $assignee,
            NotificationType::CommunicationMessageReceived,
            __('New message: :subject', ['subject' => $thread->subject]),
            Str::limit($event->message->body, 140),
            route('communication.threads.show', $thread),
        );
    }
}
