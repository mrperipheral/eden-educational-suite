<?php

namespace App\Enums;

use App\Models\Announcement;

/**
 * Who an {@see Announcement} is targeted at (M18,
 * `docs/communication.md`). Coarse, role-shaped buckets — the existing
 * architecture has no generic "school group" concept beyond the academic
 * structure (levels/arms), so level/arm-scoped targeting is a deliberate,
 * documented limitation rather than built here.
 */
enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case AllStaff = 'all_staff';
    case Teachers = 'teachers';
    case Parents = 'parents';
    case Students = 'students';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('Everyone'),
            self::AllStaff => __('All staff'),
            self::Teachers => __('Teachers'),
            self::Parents => __('Parents'),
            self::Students => __('Students'),
        };
    }

    /**
     * Whether a member holding $role is an intended recipient of an
     * announcement targeted at this audience.
     */
    public function includesRole(Role $role): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::AllStaff => in_array($role, [Role::SchoolAdmin, Role::Principal, Role::Bursar, Role::Teacher, Role::Staff], true),
            self::Teachers => $role === Role::Teacher,
            self::Parents => $role === Role::Parent,
            self::Students => $role === Role::Student,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
