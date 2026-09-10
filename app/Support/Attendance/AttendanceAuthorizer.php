<?php

namespace App\Support\Attendance;

use App\Enums\Permission;
use App\Enums\TeacherAssignmentStatus;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Decides whether a user may **record** attendance for a specific class.
 *
 * The coarse gate is `attendance.record` (a route `->can()` check). This adds
 * the class-scoping rule the M4 permissions cannot express on their own:
 *
 *   - `attendance.manage` (School Admin, Principal) → any class in the school;
 *   - `attendance.record` **without** `attendance.manage` (Teacher) → only a
 *     class they hold an **active** M11 {@see TeacherAssignment} for.
 *
 * All queries are tenant-scoped. Teacher assignments merely *scope* who a
 * teacher may mark — attendance itself does not depend on the Staff module: a
 * school with Staff off still records attendance through its
 * `attendance.manage` holders.
 */
class AttendanceAuthorizer
{
    public function canRecordForClass(User $user, int $levelId, int $armId): bool
    {
        if (! $user->hasPermission(Permission::AttendanceRecord)) {
            return false;
        }

        if ($user->hasPermission(Permission::AttendanceManage)) {
            return true;
        }

        return $this->isAssignedToClass($user, $levelId, $armId);
    }

    private function isAssignedToClass(User $user, int $levelId, int $armId): bool
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
