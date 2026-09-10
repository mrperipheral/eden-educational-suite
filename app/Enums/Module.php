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
    case Timetable = 'timetable';
    case Attendance = 'attendance';
    case Assessments = 'assessments';
    case Results = 'results';
    case Fees = 'fees';
    case LearningMaterials = 'learning-materials';
    case Cbt = 'cbt';
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
            self::Timetable => __('Timetable'),
            self::Attendance => __('Attendance'),
            self::Assessments => __('Assessments'),
            self::Results => __('Results & Report Cards'),
            self::Fees => __('Fees & Payments'),
            self::LearningMaterials => __('Learning Materials'),
            self::Cbt => __('CBT / Online Examinations'),
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
            self::Timetable => __('Weekly period timetables for classes and teachers.'),
            self::Attendance => __('Daily and per-lesson student attendance registers.'),
            self::Assessments => __('Continuous assessment and examination score entry.'),
            self::Results => __('Termly report cards compiled from assessment scores.'),
            self::Fees => __('Fee structures, invoices and payment tracking.'),
            self::LearningMaterials => __('Notes, documents and resources shared with classes.'),
            self::Cbt => __('Computer-based tests sat online by students.'),
            self::Notifications => __('Email and in-app notices to staff, parents and students.'),
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
            self::Students, self::Guardians, self::Staff => __('People'),
            self::Academics, self::Timetable, self::Attendance, self::LearningMaterials => __('Academics'),
            self::Assessments, self::Results, self::Cbt => __('Assessment'),
            self::Fees => __('Finance'),
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
            self::Guardians => [self::Students],
            self::Timetable => [self::Academics],
            self::Attendance => [self::Students],
            self::Assessments => [self::Academics, self::Students],
            self::Results => [self::Assessments],
            self::Fees => [self::Students],
            self::LearningMaterials => [self::Academics],
            self::Cbt => [self::Assessments],
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
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            default => false,
        };
    }

    /**
     * Sensible default for a newly onboarded school. Core management modules are
     * on; specialised ones (timetable, learning materials, CBT) start off. A
     * school with no stored preference for a module uses this value — no row is
     * written at onboarding.
     */
    public function enabledByDefault(): bool
    {
        return match ($this) {
            self::Timetable, self::LearningMaterials, self::Cbt => false,
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
        $order = [__('People'), __('Academics'), __('Assessment'), __('Finance'), __('Communication'), __('Portals')];

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
