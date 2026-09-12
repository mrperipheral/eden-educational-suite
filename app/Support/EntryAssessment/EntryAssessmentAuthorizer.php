<?php

namespace App\Support\EntryAssessment;

use App\Enums\Permission;
use App\Enums\TeacherAssignmentStatus;
use App\Models\EntryAssessment;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides whether a user may **create/edit** an Entry / Placement Assessment
 * record for a specific class + subject — the class/subject-scoping rule
 * the M4 permissions cannot express on their own, mirroring
 * `App\Support\Cbt\CbtAuthorizer::canAuthorFor()` exactly:
 *
 *   - `placement.manage` (School Admin, Principal) → any class/subject;
 *   - `placement.record` **without** `.manage` (Teacher) → only a
 *     `(level, subject)` they hold an **active** M11 `TeacherAssignment`
 *     for (that arm, or an arm-agnostic assignment).
 *
 * All queries are tenant-scoped. *Viewing* the list stays gated by the
 * coarse `placement.view` permission alone — not further scoped by
 * teaching assignment, the same choice M23/M24 make for the CBT/Question
 * Bank list. See `docs/entry-placement-assessment.md`.
 */
class EntryAssessmentAuthorizer
{
    public function canManageFor(User $user, int $levelId, ?int $armId, int $subjectId): bool
    {
        if (! $user->hasPermission(Permission::EntryAssessmentRecord)) {
            return false;
        }

        if ($user->hasPermission(Permission::EntryAssessmentManage)) {
            return true;
        }

        return $this->teacherAssignedTo($user, $levelId, $armId, $subjectId);
    }

    /** Whether `$user` may edit/archive this specific, already-existing record. */
    public function canManage(User $user, EntryAssessment $assessment): bool
    {
        return $this->canManageFor($user, $assessment->academic_level_id, $assessment->level_arm_id, $assessment->subject_id);
    }

    private function teacherAssignedTo(User $user, int $levelId, ?int $armId, int $subjectId): bool
    {
        $teacherId = $this->teacherIdFor($user);

        if ($teacherId === null) {
            return false;
        }

        return TeacherAssignment::query()
            ->where('status', TeacherAssignmentStatus::Active->value)
            ->where('teacher_id', $teacherId)
            ->where('academic_level_id', $levelId)
            ->where('subject_id', $subjectId)
            ->where(fn ($q) => $q->whereNull('level_arm_id')->orWhere('level_arm_id', $armId))
            ->exists();
    }

    /** The teacher record linked to this user in the active school, if any. */
    public function teacherIdFor(User $user): ?int
    {
        return Teacher::query()->where('user_id', $user->getKey())->value('id');
    }

    /**
     * The (level, subject) pairs `$user` may record for without holding
     * `.manage` — used to restrict a Teacher's create-form options to
     * classes they actually teach.
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
            ->active()
            ->where('teacher_id', $teacherId)
            ->with(['level', 'arm', 'subject'])
            ->get();
    }
}
