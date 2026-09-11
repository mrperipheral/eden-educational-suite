<?php

namespace App\Enums;

/**
 * A delivery channel a notification could, in future, go out on (M18,
 * `docs/communication.md`). Only `InApp` is implemented in this milestone —
 * the rest are the forward-looking abstraction the spec asks for, deliberately
 * without any provider implementation. Wiring a real WhatsApp/SMS/email
 * provider behind one of these is future work; adding a case here is not
 * itself an integration.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::InApp => __('In-app'),
            self::Whatsapp => __('WhatsApp'),
            self::Sms => __('SMS'),
            self::Email => __('Email'),
        };
    }

    /** Whether this channel actually delivers anything yet. */
    public function isImplemented(): bool
    {
        return $this === self::InApp;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
