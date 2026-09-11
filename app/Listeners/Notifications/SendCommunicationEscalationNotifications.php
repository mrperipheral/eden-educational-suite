<?php

namespace App\Listeners\Notifications;

use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Events\CommunicationThreadEscalated;
use App\Services\Notifications\NotificationDispatcher;

/**
 * Notifies whoever can act on an escalation (`communication.manage` holders —
 * Principal / School Admin) when a thread is escalated.
 */
class SendCommunicationEscalationNotifications
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(CommunicationThreadEscalated $event): void
    {
        $thread = $event->thread;

        $this->dispatcher->sendToUsers(
            $this->dispatcher->usersWithPermission(Permission::CommunicationManage),
            NotificationType::CommunicationEscalated,
            __('Escalated: :subject', ['subject' => $thread->subject]),
            __('A communication thread was escalated and needs attention.'),
            route('communication.threads.show', $thread),
        );
    }
}
