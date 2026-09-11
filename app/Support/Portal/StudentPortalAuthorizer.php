<?php

namespace App\Support\Portal;

use App\Models\Student;
use App\Models\User;

/**
 * The single seam every Student Portal controller uses to answer "is this
 * signed-in user actually this student?" (see `docs/student-portal.md`).
 * Mirrors `App\Support\Portal\ParentPortalAuthorizer` (M16) — a `User` is not
 * automatically a `Student` (see `App\Models\Student::user()`); the link is
 * resolved **in the active school** (`Student` is `BelongsToSchool`, so this
 * is already tenant-scoped — no second tenancy mechanism). Unlike a guardian,
 * a student has **at most one** linked `Student` record, so there is no
 * "which of several" question — only "is this really them."
 */
class StudentPortalAuthorizer
{
    /**
     * The student record this user is linked to in the active school, if any.
     */
    public function studentFor(User $user): ?Student
    {
        return Student::query()->where('user_id', $user->getKey())->first();
    }

    /**
     * The one student `$studentId` resolves to, **only** if it is this
     * signed-in user's own linked record — never trusts the id alone.
     * Returns `null` (callers `abort(404)`) for any other student, including
     * a cross-school id (`Student` is itself `BelongsToSchool`).
     */
    public function authorizedStudent(User $user, int $studentId): ?Student
    {
        $student = $this->studentFor($user);

        return $student !== null && $student->getKey() === $studentId ? $student : null;
    }

    public function canAccessStudent(User $user, int $studentId): bool
    {
        return $this->authorizedStudent($user, $studentId) !== null;
    }
}
