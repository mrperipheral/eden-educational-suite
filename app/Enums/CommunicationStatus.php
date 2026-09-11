<?php

namespace App\Enums;

use App\Models\CommunicationThread;

/**
 * A {@see CommunicationThread}'s lifecycle (M18,
 * `docs/communication.md`).
 *
 * `Open` — active, awaiting or receiving replies.
 * `Resolved` — the matter is settled; may be reopened if it recurs.
 * `Escalated` — raised to a manager (Principal / School Admin) for
 * attention; also reopenable back to `Open` once handled.
 *
 * Nothing here is ever hard-deleted — the status column is the only lifecycle
 * signal, and every transition is kept for the historical record.
 */
enum CommunicationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Resolved => __('Resolved'),
            self::Escalated => __('Escalated'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Open => 'brand',
            self::Resolved => 'success',
            self::Escalated => 'warning',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
