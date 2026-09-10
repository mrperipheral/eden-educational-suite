<?php

namespace App\Support\Assessment;

use App\Enums\Permission;
use App\Enums\TeacherAssignmentStatus;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Decides whether a user may **create / record for** an assessment or assignment
 * covering a specific class + subject.
 *
 * The coarse gate is `assessment.record` (a route `->can()` check). This adds
 * the class/subject-scoping rule the M4 permissions cannot express on their own:
 *
 *   - `assessment.manage` (School Admin, Principal) → any class / subject;
 *   - `assessment.record` **without** `assessment.manage` (Teacher) → only a
 *     `(level, subject)` they hold an **active** M11 {@see TeacherAssignment}
 *     for, and only that arm (or an arm-agnostic assignment).
 *
 * All queries are tenant-scoped. Teacher assignments merely *scope* who may
 * record — assessments themselves do not depend on the Staff module: a school
 * with Staff off still records through its `assessment.manage` holders.
 */
class AssessmentAuthorizer
{
    public function canRecordFor(User $user, int $levelId, int $armId, int $subjectId): bool
    {
        if (! $user->hasPermission(Permission::AssessmentRecord)) {
            return false;
        }

        if ($user->hasPermission(Permission::AssessmentManage)) {
            return true;
        }

        return $this->teacherAssignedTo($user, $levelId, $armId, $subjectId);
    }

    private function teacherAssignedTo(User $user, int $levelId, int $armId, int $subjectId): bool
    {
        $teacherId = Teacher::query()->where('user_id', $user->getKey())->value('id');

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
}
