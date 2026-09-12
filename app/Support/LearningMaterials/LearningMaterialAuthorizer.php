<?php

namespace App\Support\LearningMaterials;

use App\Enums\Permission;
use App\Models\LearningMaterial;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides whether a user may **upload** a learning material for a specific
 * class + subject, or **delete** a given one — the class/subject-scoping
 * rule the M4 permissions cannot express on their own, mirroring
 * `App\Support\Assessment\AssessmentAuthorizer` exactly:
 *
 *   - `material.manage` (School Admin, Principal) → any class /
 *     subject, and may delete any material;
 *   - `material.upload` **without** `.manage` (Teacher) → only a
 *     `(level, subject)` they hold an **active** M11 {@see TeacherAssignment}
 *     for (that arm, or an arm-agnostic assignment), and may only delete
 *     material they uploaded themselves.
 *
 * All queries are tenant-scoped. See `docs/learning-materials.md`.
 */
class LearningMaterialAuthorizer
{
    public function canUploadFor(User $user, int $levelId, ?int $armId, int $subjectId): bool
    {
        if (! $user->hasPermission(Permission::LearningMaterialUpload)) {
            return false;
        }

        if ($user->hasPermission(Permission::LearningMaterialManage)) {
            return true;
        }

        return $this->teacherAssignedTo($user, $levelId, $armId, $subjectId);
    }

    public function canDelete(User $user, LearningMaterial $material): bool
    {
        if ($user->hasPermission(Permission::LearningMaterialManage)) {
            return true;
        }

        if (! $user->hasPermission(Permission::LearningMaterialUpload)) {
            return false;
        }

        return $material->uploaded_by === $user->getKey();
    }

    private function teacherAssignedTo(User $user, int $levelId, ?int $armId, int $subjectId): bool
    {
        $teacherId = Teacher::query()->where('user_id', $user->getKey())->value('id');

        if ($teacherId === null) {
            return false;
        }

        return TeacherAssignment::query()
            ->active()
            ->where('teacher_id', $teacherId)
            ->where('academic_level_id', $levelId)
            ->where('subject_id', $subjectId)
            ->where(fn ($q) => $q->whereNull('level_arm_id')->orWhere('level_arm_id', $armId))
            ->exists();
    }

    /**
     * The (level, subject) pairs `$user` may upload for without holding
     * `.manage` — used to restrict a Teacher's create-form options to
     * classes they actually teach. Returns an empty collection for a
     * `.manage` holder — the caller should treat "unrestricted" separately.
     *
     * @return Collection<int, TeacherAssignment>
     */
    public function assignmentsFor(User $user): Collection
    {
        $teacherId = Teacher::query()->where('user_id', $user->getKey())->value('id');

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
