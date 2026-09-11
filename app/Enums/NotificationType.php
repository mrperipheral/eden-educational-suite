<?php

namespace App\Enums;

use App\Models\Notification;
use App\Services\Notifications\NotificationDispatcher;

/**
 * The category of an in-app {@see Notification} (M18,
 * `docs/communication.md`). Deliberately small — only the events M18 itself
 * raises. Future modules add a case here plus a dispatch call; they do not
 * need to know anything about delivery channels
 * ({@see NotificationDispatcher}).
 */
enum NotificationType: string
{
    case AnnouncementPublished = 'announcement_published';
    case CommunicationMessageReceived = 'communication_message_received';
    case CommunicationEscalated = 'communication_escalated';

    public function label(): string
    {
        return match ($this) {
            self::AnnouncementPublished => __('Announcement'),
            self::CommunicationMessageReceived => __('New message'),
            self::CommunicationEscalated => __('Escalated'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
