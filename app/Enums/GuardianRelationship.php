<?php

namespace App\Enums;

use App\Models\Guardian;

/**
 * How a {@see Guardian} is related to a student, stored explicitly on
 * the `guardian_student` link (see `docs/guardian-management.md`).
 *
 * A small, closed set — enough to describe the common cases without turning the
 * link into a free-text field. `Other` is the catch-all; the guardian's `notes`
 * carry any detail.
 */
enum GuardianRelationship: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Grandparent = 'grandparent';
    case AuntUncle = 'aunt_uncle';
    case Sibling = 'sibling';
    case LegalGuardian = 'legal_guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mother => __('Mother'),
            self::Father => __('Father'),
            self::Grandparent => __('Grandparent'),
            self::AuntUncle => __('Aunt / Uncle'),
            self::Sibling => __('Sibling'),
            self::LegalGuardian => __('Legal guardian'),
            self::Other => __('Other'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
