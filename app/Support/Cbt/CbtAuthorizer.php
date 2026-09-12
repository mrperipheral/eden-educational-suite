<?php

namespace App\Support\Cbt;

use App\Enums\Permission;
use App\Enums\TeacherAssignmentStatus;
use App\Models\Examination;
use App\Models\Question;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides whether a user may **author** (create/edit-while-draft/attach
 * questions to/schedule/close) an examination for a specific class +
 * subject — the class/subject-scoping rule the M4 permissions cannot
 * express on their own, mirroring `App\Support\Assessment\
 * AssessmentAuthorizer` exactly:
 *
 *   - `cbt.manage` (School Admin, Principal) → any class/subject;
 *   - `cbt.author` **without** `cbt.manage` (Teacher) → only a
 *     `(level, subject)` they hold an **active** M11 `TeacherAssignment`
 *     for (that arm, or an arm-agnostic assignment).
 *
 * All queries are tenant-scoped. See `docs/cbt.md`.
 */
class CbtAuthorizer
{
    public function canAuthorFor(User $user, int $levelId, ?int $armId, int $subjectId): bool
    {
        if (! $user->hasPermission(Permission::CbtAuthor)) {
            return false;
        }

        if ($user->hasPermission(Permission::CbtManage)) {
            return true;
        }

        return $this->teacherAssignedTo($user, $levelId, $armId, $subjectId);
    }

    /** Whether `$user` may author/manage this specific, already-existing examination. */
    public function canManage(User $user, Examination $examination): bool
    {
        return $this->canAuthorFor($user, $examination->academic_level_id, $examination->level_arm_id, $examination->subject_id);
    }

    /**
     * Whether `$user` may create/edit a Question Bank entry for a given
     * `(subject, level, arm)` — mirrors {@see self::canAuthorFor()}
     * exactly, except a question's `level`/`arm` are optional: when
     * `$levelId` is null (a subject-only, reusable-at-any-level question),
     * a Teacher only needs **some** active assignment for that subject,
     * at any level (Milestone 24, `docs/question-bank.md`).
     */
    public function canManageQuestionFor(User $user, int $subjectId, ?int $levelId, ?int $armId): bool
    {
        if (! $user->hasPermission(Permission::CbtAuthor)) {
            return false;
        }

        if ($user->hasPermission(Permission::CbtManage)) {
            return true;
        }

        $teacherId = $this->teacherIdFor($user);

        if ($teacherId === null) {
            return false;
        }

        $query = TeacherAssignment::query()
            ->where('status', TeacherAssignmentStatus::Active->value)
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId);

        if ($levelId !== null) {
            $query->where('academic_level_id', $levelId);

            if ($armId !== null) {
                $query->where(fn ($q) => $q->whereNull('level_arm_id')->orWhere('level_arm_id', $armId));
            }
        }

        return $query->exists();
    }

    /** Whether `$user` may edit/archive this specific, already-existing Question Bank entry. */
    public function canManageQuestion(User $user, Question $question): bool
    {
        return $this->canManageQuestionFor($user, $question->subject_id, $question->academic_level_id, $question->level_arm_id);
    }

    /**
     * Whether `$student` is eligible to see/take this examination — their
     * *current* enrollment must exactly match its session/level/arm. Never
     * trusts a submitted student/exam id alone; the caller still resolves
     * both through tenant-scoped queries first.
     */
    public function studentCanAccess(Student $student, Examination $examination): bool
    {
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null) {
            return false;
        }

        return $enrollment->academic_session_id === $examination->academic_session_id
            && $enrollment->academic_level_id === $examination->academic_level_id
            && $enrollment->level_arm_id === $examination->level_arm_id;
    }

    private function teacherAssignedTo(User $user, int $levelId, ?int $armId, int $subjectId): bool
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

    /**
     * The (level, subject) pairs `$user` may author for without holding
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
