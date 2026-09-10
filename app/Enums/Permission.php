<?php

namespace App\Enums;

/**
 * The application's authorization vocabulary.
 *
 * Permissions are **code-defined and static** — there is no `permissions` table.
 * They are the only thing controllers, policies and Blade should check
 * ("can this user do X?"), never role names.
 *
 * Each permission is registered as a Gate ability (see `AuthServiceProvider`)
 * whose check is composed with the active `TenantContext` — a permission only
 * ever applies *within the school the user is currently working in*.
 *
 * Coarse on purpose. A later domain milestone may split e.g. `student.manage`
 * into finer permissions; adding a case here + slotting it into the relevant
 * Role bundles is the whole change.
 */
enum Permission: string
{
    // ---- School configuration -------------------------------------------------
    case SchoolSettingsView = 'school.settings.view';
    case SchoolSettingsUpdate = 'school.settings.update';

    // ---- People & access (enforced: Members M4/M5) --------------------------
    case MemberView = 'member.view';
    case MemberAssignRole = 'member.assign-role';
    case MemberRemove = 'member.remove';

    // ---- Academic structure (enforced: Academic Foundation M8) --------------
    case AcademicsView = 'academics.view';
    case AcademicsManage = 'academics.manage';

    // ---- Students (enforced: Student Management M9) ------------------------
    case StudentView = 'student.view';
    case StudentManage = 'student.manage';

    // ---- Declared for later domain milestones (coarse; not yet enforced) -----
    case GuardianView = 'guardian.view';
    case GuardianManage = 'guardian.manage';
    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';
    case AttendanceView = 'attendance.view';
    case AttendanceRecord = 'attendance.record';
    case ResultView = 'result.view';
    case ResultEnter = 'result.enter';
    case ResultPublish = 'result.publish';
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case PortalParent = 'portal.parent';
    case PortalStudent = 'portal.student';

    public function label(): string
    {
        return ucfirst(str_replace(['.', '-'], [' — ', ' '], $this->value));
    }

    /**
     * @return list<Permission>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
