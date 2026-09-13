<?php

namespace App\Support\Reports;

use App\Enums\TeacherAssignmentStatus;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The one seam every M27 report uses to decide "does this user see the
 * whole school's data, or only their own classes?" — generalises the exact
 * `(level, subject[, arm])` active-`TeacherAssignment` scoping pattern
 * already established by `App\Support\Assessment\AssessmentAuthorizer`,
 * `App\Support\Cbt\CbtAuthorizer` and `App\Support\LearningMaterials\
 * LearningMaterialAuthorizer` — not a second authorization system, the same
 * table, the same rule, read-only. See `docs/reporting.md`.
 *
 * A report never calls this for the coarse "may you open this report at
 * all" question — that stays a plain `Permission` Gate check
 * (`reports.view` + the domain's own `.view`/`.report`/`.manage`
 * permission) in the controller. This class only answers the *narrower*
 * follow-up: once inside, should the query be restricted to the acting
 * user's own assigned classes?
 */
class ReportAuthorizer
{
    /** The teacher record linked to this user in the active school, if any. */
    public function teacherIdFor(User $user): ?int
    {
        return Teacher::query()->where('user_id', $user->getKey())->value('id');
    }

    /**
     * This user's active teaching assignments, eager-loaded with level/arm/
     * subject — the raw material every report-scoping decision is built
     * from.
     *
     * @return Collection<int, TeacherAssignment>
     */
    public function assignmentsFor(User $user): Collection
    {
        $teacherId = $this->teacherIdFor($user);

        if ($teacherId === null) {
            return collect();
        }

        return TeacherAssignment::query()
            ->where('status', TeacherAssignmentStatus::Active->value)
            ->where('teacher_id', $teacherId)
            ->with(['level', 'arm', 'subject'])
            ->get();
    }

    /**
     * The distinct academic level ids this user is actively assigned to
     * teach — used to scope a report's `whereIn('academic_level_id', ...)`
     * for a Teacher who does not hold the domain's "manage" tier.
     *
     * @return list<int>
     */
    public function levelIdsFor(User $user): array
    {
        return $this->assignmentsFor($user)->pluck('academic_level_id')->unique()->values()->all();
    }

    /**
     * The distinct subject ids this user is actively assigned to teach.
     *
     * @return list<int>
     */
    public function subjectIdsFor(User $user): array
    {
        return $this->assignmentsFor($user)->pluck('subject_id')->unique()->values()->all();
    }
}
