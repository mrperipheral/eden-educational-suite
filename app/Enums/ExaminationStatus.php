<?php

namespace App\Enums;

/**
 * An examination's own administrative lifecycle (Milestone 23,
 * `docs/cbt.md`) — deliberately separate from a student's own
 * {@see ExamAttemptStatus}. One-way, like `ResultRunStatus`: `Draft` →
 * `Scheduled` → `Closed`, no reopening.
 *
 * `Scheduled` means "made available to students" — it does **not** by
 * itself mean an attempt can start right now: `Examination::isWithinWindow()`
 * additionally checks `starts_at`/`ends_at` against the server clock. A
 * `Scheduled` exam outside its own time window simply isn't startable yet
 * (or any more); staff use {@see self::Closed} to end it explicitly and
 * permanently regardless of `ends_at`.
 */
enum ExaminationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Closed => __('Closed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'success',
            self::Closed => 'gray',
        };
    }

    /** Whether questions may still be added/removed/reordered. */
    public function structureEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Whether the exam's own metadata (title, timing, pass mark, …) may still be edited. */
    public function metadataEditable(): bool
    {
        return $this === self::Draft;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
