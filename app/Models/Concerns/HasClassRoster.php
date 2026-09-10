<?php

namespace App\Models\Concerns;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared "who is in this class on this date" rule for {@see Assessment}
 * and {@see Assignment} (see `docs/assessment-management.md` §
 * eligibility). Identical in spirit to the M13 attendance rule.
 *
 * A student is eligible iff they hold an {@see Enrollment} for this
 * record's exact `(academic_session_id, academic_level_id, level_arm_id)` whose
 * date range `[started_on, ended_on]` contains {@see self::rosterDate()}. The
 * student's / enrollment's *current* status is deliberately not a filter: a
 * student who withdraws **after** the date was in class that day and stays on
 * the roster; one whose enrollment ended **before** the date is excluded.
 */
trait HasClassRoster
{
    /** The date the roster is taken as of (ISO `Y-m-d`). */
    abstract public function rosterDate(): string;

    /**
     * @return Builder<Student>
     */
    public function eligibleStudents(): Builder
    {
        $date = $this->rosterDate();

        return Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q
                ->where('academic_session_id', $this->academic_session_id)
                ->where('academic_level_id', $this->academic_level_id)
                ->where('level_arm_id', $this->level_arm_id)
                ->where('started_on', '<=', $date)
                ->where(fn (Builder $q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $date)))
            ->ordered();
    }

    /**
     * Whether any student is eligible — a cheap existence check for validation.
     */
    public function classHasEligibleStudents(): bool
    {
        return $this->eligibleStudents()->exists();
    }
}
