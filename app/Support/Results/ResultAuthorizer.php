<?php

namespace App\Support\Results;

use App\Enums\Permission;
use App\Enums\TeacherAssignmentStatus;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Decides whether a user may add the **class-teacher comment** on a
 * {@see StudentResult} within a given result run's class.
 *
 * The coarse gate is `result.enter` (a route `->can()` check). This adds the
 * class-scoping rule M4 permissions cannot express: a Teacher may only comment
 * on a class they hold an **active** M11 {@see TeacherAssignment} for (any
 * subject — a class-teacher comment is class-wide, not subject-specific).
 * `result.manage` (School Admin, Principal) may comment on any class.
 *
 * Compiling / reviewing / approving / publishing / locking a run, and the
 * result-adjustment workflow, are **not** class-scoped — only School Admin and
 * Principal ever hold `result.manage` / `result.publish` / `result.adjust`, so
 * a plain `->can()` check on the route is sufficient for those.
 */
class ResultAuthorizer
{
    public function canCommentOnRun(User $user, ResultRun $run): bool
    {
        if (! $user->hasPermission(Permission::ResultEnter)) {
            return false;
        }

        if ($user->hasPermission(Permission::ResultManage)) {
            return true;
        }

        return $this->teacherAssignedToClass($user, $run->academic_level_id, $run->level_arm_id);
    }

    private function teacherAssignedToClass(User $user, int $levelId, int $armId): bool
    {
        $teacherId = Teacher::query()->where('user_id', $user->getKey())->value('id');

        if ($teacherId === null) {
            return false;
        }

        return TeacherAssignment::query()
            ->where('status', TeacherAssignmentStatus::Active->value)
            ->where('teacher_id', $teacherId)
            ->where('academic_level_id', $levelId)
            ->where(fn ($q) => $q->whereNull('level_arm_id')->orWhere('level_arm_id', $armId))
            ->exists();
    }
}
