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

    // ---- Guardians (enforced: Guardian Management M10) --------------------
    case GuardianView = 'guardian.view';
    case GuardianManage = 'guardian.manage';

    // ---- Teachers / staff (enforced: Teacher Management M11) --------------
    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';

    // ---- Timetable (enforced: Timetable Management M12) -------------------
    case TimetableView = 'timetable.view';
    case TimetableManage = 'timetable.manage';

    // ---- Attendance (enforced: Attendance Management M13) ----------------
    case AttendanceView = 'attendance.view';
    case AttendanceRecord = 'attendance.record';
    case AttendanceManage = 'attendance.manage';

    // ---- Assessments & assignments (enforced: Assessment & Assignments M14) ----
    case AssessmentView = 'assessment.view';
    case AssessmentRecord = 'assessment.record';
    case AssessmentManage = 'assessment.manage';

    // ---- Results & report cards (enforced: Results & Report Cards M15) ------
    case ResultView = 'result.view';
    case ResultEnter = 'result.enter';
    case ResultManage = 'result.manage';
    case ResultPublish = 'result.publish';
    case ResultAdjust = 'result.adjust';

    // ---- Promotion & graduation (enforced: Promotion & Graduation M21) ------
    case PromotionView = 'promotion.view';
    case PromotionManage = 'promotion.manage';
    case GraduationManage = 'graduation.manage';

    // ---- Fees & payments (enforced: Fees & Fee Management M19) --------------
    case FeesView = 'fees.view';
    case FeesManage = 'fees.manage';
    case FeesRecordPayment = 'fees.record-payment';
    case FeesAdjust = 'fees.adjust';
    case FeesReport = 'fees.report';

    // ---- Learning materials (enforced: Learning Materials M22) --------------
    case LearningMaterialView = 'material.view';
    case LearningMaterialUpload = 'material.upload';
    case LearningMaterialManage = 'material.manage';

    // ---- CBT / online examinations (enforced: CBT / Online Examinations M23) ----
    case CbtView = 'cbt.view';
    case CbtAuthor = 'cbt.author';
    case CbtManage = 'cbt.manage';
    case CbtTake = 'cbt.take';

    // ---- Communication & notifications (enforced: Communication & Notification Foundation M18) ----
    case CommunicationView = 'communication.view';
    case CommunicationCreate = 'communication.create';
    case CommunicationManage = 'communication.manage';
    case CommunicationResolve = 'communication.resolve';
    case CommunicationEscalate = 'communication.escalate';
    case AnnouncementView = 'announcement.view';
    case AnnouncementManage = 'announcement.manage';

    // ---- Declared for later domain milestones (coarse; not yet enforced) -----
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
