<?php

namespace App\Enums;

use App\Events\AnnouncementPublished;
use App\Models\Announcement;

/**
 * An {@see Announcement}'s publication lifecycle (M18,
 * `docs/communication.md`). `Draft` is visible to managers only; `Published`
 * is visible to its target audience and fires the
 * {@see AnnouncementPublished} notification event.
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
