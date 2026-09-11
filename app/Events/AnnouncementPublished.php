<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once, from {@see Announcement::publish()}. Triggers the notification
 * fan-out to the announcement's target audience — see
 * `App\Listeners\Notifications\SendAnnouncementNotifications` (auto-discovered
 * by Laravel's event discovery, no manual `Event::listen()` needed) and
 * `docs/communication.md`. Not queued: dispatch is synchronous, matching the
 * rest of the app (no queue infrastructure introduced for M18).
 */
class AnnouncementPublished
{
    use Dispatchable;

    public function __construct(public readonly Announcement $announcement) {}
}
