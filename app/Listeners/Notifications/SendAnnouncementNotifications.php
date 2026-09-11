<?php

namespace App\Listeners\Notifications;

use App\Events\AnnouncementPublished;
use App\Services\Notifications\NotificationDispatcher;

class SendAnnouncementNotifications
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(AnnouncementPublished $event): void
    {
        $this->dispatcher->notifyAnnouncementAudience($event->announcement);
    }
}
