<?php

namespace App\Enums;

/**
 * The fixed set of per-school roles.
 *
 * A role is nothing more than a **named bundle of {@see Permission}s** plus a
 * `tier` used to stop privilege escalation during role assignment. Roles are
 * assigned per school (on the `school_user` pivot) — a user can be a Teacher at
 * one school and a Parent at another.
 *
 * Platform-wide administration is a separate concept
 * (`users.is_platform_admin`), not a role — see `docs/authorization.md`.
 */
enum Role: string
{
    case SchoolAdmin = 'school_admin';
    case Principal = 'principal';
    case Bursar = 'bursar';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Parent = 'parent';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SchoolAdmin => 'School Admin',
            self::Principal => 'Principal',
            self::Bursar => 'Accountant / Bursar',
            self::Teacher => 'Teacher',
            self::Staff => 'Staff',
            self::Parent => 'Parent',
            self::Student => 'Student',
        };
    }

    /**
     * Organisational rank. Role assignment may only grant a role whose tier is
     * ≤ the granter's own — you can never hand out a role more powerful than
     * yours. Distinct tiers are not a permission hierarchy (a Bursar and a
     * Teacher share tier 50 but hold different permissions).
     */
    public function tier(): int
    {
        return match ($this) {
            self::SchoolAdmin => 100,
            self::Principal => 80,
            self::Bursar, self::Teacher => 50,
            self::Staff => 30,
            self::Parent, self::Student => 10,
        };
    }

    /**
     * The permissions this role grants, within the school it is held in.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SchoolAdmin => Permission::all(),

            self::Principal => [
                Permission::SchoolSettingsView,
                Permission::MemberView,
                Permission::MemberAssignRole,
                Permission::StudentView,
                Permission::StudentManage,
                Permission::GuardianView,
                Permission::GuardianManage,
                Permission::StaffView,
                Permission::AcademicsView,
                Permission::AcademicsManage,
                Permission::AttendanceView,
                Permission::AttendanceRecord,
                Permission::ResultView,
                Permission::ResultEnter,
                Permission::ResultPublish,
                Permission::FinanceView,
            ],

            self::Bursar => [
                Permission::SchoolSettingsView,
                Permission::StudentView,
                Permission::GuardianView,
                Permission::FinanceView,
                Permission::FinanceManage,
            ],

            self::Teacher => [
                Permission::StudentView,
                Permission::GuardianView,
                Permission::AcademicsView,
                Permission::AttendanceView,
                Permission::AttendanceRecord,
                Permission::ResultView,
                Permission::ResultEnter,
            ],

            self::Staff => [
                Permission::StudentView,
                Permission::GuardianView,
                Permission::AcademicsView,
                Permission::AttendanceView,
            ],

            self::Parent => [
                Permission::PortalParent,
            ],

            self::Student => [
                Permission::PortalStudent,
            ],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return list<Role>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
