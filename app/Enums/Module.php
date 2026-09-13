<?php

namespace App\Enums;

/**
 * The application's catalogue of optional feature modules.
 *
 * A "module" is a coarse area of functionality a school can turn on or off
 * independently (see `docs/module-activation.md`). The catalogue is
 * **code-defined and static** — there is no `modules` table. Adding a module is
 * a new case here plus its metadata; nothing else in the activation system
 * changes.
 *
 * Module activation is **configuration, not authorization**. Whether a module is
 * enabled says nothing about what a user may do inside it — that stays with
 * `App\Enums\Permission` / the Gate. A future domain route typically checks
 * both: `->middleware('module:attendance')` (is the feature on for this school?)
 * **and** `->can('attendance.record')` (may this user do it?).
 *
 * The enum case order is the catalogue's display order.
 */
enum Module: string
{
    case Academics = 'academics';
    case Students = 'students';
    case Guardians = 'guardians';
    case Staff = 'staff';
    case Promotion = 'promotion';
    case Timetable = 'timetable';
    case Attendance = 'attendance';
    case Assessments = 'assessments';
    case Results = 'results';
    case Fees = 'fees';
    case LearningMaterials = 'learning-materials';
    case Cbt = 'cbt';
    case EntryAssessment = 'entry-assessment';
    case Reports = 'reports';
    case Notifications = 'notifications';
    case ParentPortal = 'parent-portal';
    case StudentPortal = 'student-portal';

    public function label(): string
    {
        return match ($this) {
            self::Academics => __('Academic Management'),
            self::Students => __('Student Management'),
            self::Guardians => __('Parent / Guardian Management'),
            self::Staff => __('Teacher / Staff Management'),
            self::Promotion => __('Promotion & Graduation'),
            self::Timetable => __('Timetable'),
            self::Attendance => __('Attendance'),
            self::Assessments => __('Assessments'),
            self::Results => __('Results & Report Cards'),
            self::Fees => __('Fees & Payments'),
            self::LearningMaterials => __('Learning Materials'),
            self::Cbt => __('CBT / Online Examinations'),
            self::EntryAssessment => __('Entry / Placement Assessment'),
            self::Reports => __('Reporting & Analytics'),
            self::Notifications => __('Notifications'),
            self::ParentPortal => __('Parent Portal'),
            self::StudentPortal => __('Student Portal'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Academics => __('Classes, sections and subjects — the academic structure everything else hangs from.'),
            self::Students => __('Student records, enrolment and class placement.'),
            self::Guardians => __('Parent and guardian records linked to their children.'),
            self::Staff => __('Teacher and non-teaching staff records and assignments.'),
            self::Promotion => __('Promote students between sessions/classes and graduate them, preserving history.'),
            self::Timetable => __('Weekly period timetables for classes and teachers.'),
            self::Attendance => __('Daily class attendance registers, independent of the timetable.'),
            self::Assessments => __('Assessment score entry and class assignments — the source data for results.'),
            self::Results => __('Termly report cards compiled from assessment scores.'),
            self::Fees => __('Fee structures, invoices and payment tracking.'),
            self::LearningMaterials => __('Notes, documents and resources shared with classes.'),
            self::Cbt => __('Computer-based tests sat online by students.'),
            self::EntryAssessment => __('Records assessments conducted for prospective or newly admitted students — not a placement decision.'),
            self::Reports => __('Dashboard analytics and administrative reports drawn from the school\'s own enabled modules.'),
            self::Notifications => __('Communication threads, announcements and in-app notices to staff, parents and students.'),
            self::ParentPortal => __('The portal parents sign in to for their children.'),
            self::StudentPortal => __('The portal students sign in to for their own records.'),
        };
    }

    /**
     * Grouping for the administration screen. Purely presentational.
     */
    public function group(): string
    {
        return match ($this) {
            self::Students, self::Guardians, self::Staff, self::Promotion => __('People'),
            self::Academics, self::Timetable, self::Attendance, self::LearningMaterials => __('Academics'),
            self::Assessments, self::Results, self::Cbt, self::EntryAssessment => __('Assessment'),
            self::Fees => __('Finance'),
            self::Reports => __('Reporting'),
            self::Notifications => __('Communication'),
            self::ParentPortal, self::StudentPortal => __('Portals'),
        };
    }

    /**
     * Modules this one needs in order to function. Kept deliberately shallow —
     * a single level of "X reads data owned by Y". Enforced when toggling
     * (see `App\Http\Controllers\SchoolModuleController`).
     *
     * @return list<self>
     */
    public function dependencies(): array
    {
        return match ($this) {
            self::Students => [self::Academics],
            self::Guardians => [self::Students],
            self::Staff => [self::Academics],
            self::Promotion => [self::Students],
            self::Timetable => [self::Academics, self::Staff],
            self::Attendance => [self::Academics, self::Students],
            self::Assessments => [self::Academics, self::Students],
            self::Results => [self::Assessments],
            self::Fees => [self::Students],
            self::LearningMaterials => [self::Academics],
            self::Cbt => [self::Assessments],
            self::EntryAssessment => [self::Academics],
            self::Reports => [self::Academics],
            self::ParentPortal => [self::Guardians],
            self::StudentPortal => [self::Students],
            default => [],
        };
    }

    /**
     * Whether the functionality behind this module has actually been built. A
     * domain milestone flips its own module to `true` the moment it ships a
     * usable feature; until then the toggle is a forward-looking preference and
     * the UI badges the module "Planned".
     *
     *   - `academics` — Academic Foundation (M8): sessions, periods, levels,
     *     arms, subjects (`docs/academic-foundation.md`).
     *   - `students` — Student Management (M9): student records + enrollment
     *     history (`docs/student-management.md`).
     *   - `guardians` — Guardian / Parent Management (M10): guardian records +
     *     student ↔ guardian relationships (`docs/guardian-management.md`).
     *   - `staff` — Teacher Management (M11): teacher records, optional account
     *     link, teaching-assignment foundation (`docs/teacher-management.md`).
     *   - `timetable` — Timetable Management (M12): weekly class/teacher
     *     schedules with conflict detection (`docs/timetable-management.md`).
     *     Still **off** by default — a specialised tool a school opts into.
     *   - `attendance` — Attendance Management (M13): daily class registers with
     *     a draft → submitted lifecycle (`docs/attendance-management.md`).
     *     Independent of the Timetable module.
     *   - `assessments` — Assessment & Assignments (M14): configurable
     *     assessment categories, assessments with a draft → published → locked
     *     lifecycle and bulk score entry, plus class assignments with
     *     completion tracking (`docs/assessment-management.md`). Depends on
     *     Academics + Students only — not Timetable, Attendance, Results or CBT.
     *   - `results` — Results & Report Cards (M15): configurable grading and
     *     weighting schemes, a draft → compiled → reviewed → approved →
     *     published → locked result run compiled from **locked** M14
     *     assessment scores, class position, and a configurable, printable
     *     report card (`docs/results-report-cards.md`). Depends on
     *     `assessments` only — not Timetable, Attendance or CBT.
     *   - `parent-portal` — Parent Portal (M16): a secure, read-only,
     *     child-scoped window for a signed-in parent onto their own
     *     children's published data (results, report cards, attendance,
     *     assignments, timetable) — see `docs/parent-portal.md`. Depends on
     *     `guardians` only.
     *   - `student-portal` — Student Portal (M17): the same shape as the
     *     Parent Portal, but for the student's own record directly — see
     *     `docs/student-portal.md`. Depends on `students` only.
     *   - `notifications` — Communication & Notification Foundation (M18): a
     *     staff-facing Communication Hub (school-scoped threads/messages with
     *     an open → resolved/escalated lifecycle), school-scoped announcements
     *     with audience targeting, and an in-app notification centre shared by
     *     staff and the Parent/Student portals (`docs/communication.md`).
     *     Depends on nothing else — it reads across modules but does not
     *     require any of them to be on.
     *   - `fees` — Fees & Fee Management (M19): school-configured fee
     *     categories + fee structures (session/period/level/optional arm),
     *     student-specific charges snapshotted from a structure (immune to
     *     a later structure edit), manual payment recording with
     *     allocation to one or more charges, and a server-calculated fee
     *     statement shared by staff and the Parent/Student portals
     *     (`docs/fees.md`). Online payment (Paystack) is **not** built here
     *     — M20. Depends on `students` only.
     *   - `promotion` — Promotion & Graduation (M21): bulk-promotes eligible
     *     students from a source session/level/arm to a target one by
     *     creating a new `Enrollment` (the source one is preserved, closed
     *     exactly as an ordinary class change already does), and an
     *     explicit graduation action that transitions `StudentStatus` while
     *     preserving every historical enrollment and result
     *     (`docs/promotion.md`). No automatic pass/fail rules — every
     *     promotion/graduation is an authorised administrative decision.
     *     Depends on `students` only — not fees, CBT or learning materials.
     *   - `learning-materials` — Learning Materials (M22): a teacher/admin
     *     uploads a single file (PDF, image or audio — video is declared but
     *     disabled) for a subject + class, students in that class can view
     *     and download it (`docs/learning-materials.md`). No drafts,
     *     versioning or approval workflow. Depends on `academics` only.
     *   - `cbt` — CBT / Online Examinations (M23): school-scoped
     *     multiple-choice/true-false examinations with a draft → scheduled
     *     → closed lifecycle, a per-exam question snapshot (immune to a
     *     later question edit), one timed attempt per student, server-side
     *     automatic marking, and immediate or scheduled result release
     *     (`docs/cbt.md`). No essay/manual-marking questions, no proctoring.
     *     Depends on `assessments` only.
     *   - `entry-assessment` — Entry / Placement Assessment (M25): records
     *     an assessment conducted for a prospective or newly admitted
     *     student — candidate details, an assessed subject, a score against
     *     a school-defined maximum, and a free-text result label
     *     (`docs/entry-placement-assessment.md`). It does **not** make a
     *     placement decision or change a student's enrolment — recording
     *     only. Depends on `academics` only; a linked `Student` record is
     *     optional, not required.
     *   - `reports` — Reporting & Analytics (M27): dashboard KPI cards plus
     *     staff-facing academic/attendance/fee/student/staff/CBT/promotion/
     *     learning-material/communication reports, all read-only aggregates
     *     over each module's own existing data (`docs/reporting.md`). A
     *     report whose underlying module is off (or has no data yet) shows
     *     an explicit empty state, never a broken query or a misleading
     *     zero. Depends on `academics` only — every report's filters
     *     (session/period/level/arm/subject) are academic-structure
     *     concepts, but a report itself never requires any *other* domain
     *     module to be on.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Academics, self::Students, self::Guardians, self::Staff, self::Timetable, self::Attendance, self::Assessments, self::Results, self::Notifications, self::Fees, self::Promotion, self::LearningMaterials, self::Cbt, self::EntryAssessment, self::Reports, self::ParentPortal, self::StudentPortal => true,
            default => false,
        };
    }

    /**
     * Sensible default for a newly onboarded school. Core management modules are
     * on; specialised ones (timetable, learning materials, CBT, entry/placement
     * assessment) start off. A school with no stored preference for a module
     * uses this value — no row is written at onboarding.
     */
    public function enabledByDefault(): bool
    {
        return match ($this) {
            self::Timetable, self::LearningMaterials, self::Cbt, self::EntryAssessment => false,
            default => true,
        };
    }

    /**
     * The catalogue grouped for display, in group order, each group in case
     * order. Empty groups are omitted.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $order = [__('People'), __('Academics'), __('Assessment'), __('Finance'), __('Reporting'), __('Communication'), __('Portals')];

        $groups = array_fill_keys($order, []);

        foreach (self::cases() as $module) {
            $groups[$module->group()][] = $module;
        }

        return array_filter($groups);
    }

    /**
     * value => default-enabled, for every module.
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $out = [];

        foreach (self::cases() as $module) {
            $out[$module->value] = $module->enabledByDefault();
        }

        return $out;
    }
}
