<?php

namespace App\Support\Portal;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single seam every Parent Portal controller uses to answer "which
 * students may this signed-in parent see?" (see `docs/parent-portal.md`).
 *
 * A `User` is not automatically a `Guardian` — the two are separate concepts
 * (see `App\Models\Guardian::user()`). Every method here resolves the
 * authenticated user's linked `Guardian` record **in the active school**
 * (`Guardian` is `BelongsToSchool`, so this is already tenant-scoped — no
 * second tenancy mechanism) and never trusts a student id supplied by the
 * request until it is proven to be one of that guardian's own linked
 * children. A parent with no linked guardian, or a guardian with no linked
 * students, simply resolves to an empty result — never an error, never
 * another school's or another family's data.
 */
class ParentPortalAuthorizer
{
    /**
     * The guardian record this user is linked to in the active school, if any.
     */
    public function guardianFor(User $user): ?Guardian
    {
        return Guardian::query()->where('user_id', $user->getKey())->first();
    }

    /**
     * Every student this parent is legitimately linked to, in the active
     * school, ordered for display. Empty when there is no linked guardian or
     * the guardian has no linked students — never another family's data.
     *
     * @return Collection<int, Student>
     */
    public function studentsFor(User $user): Collection
    {
        return $this->guardianFor($user)?->students()->ordered()->get() ?? collect();
    }

    /**
     * The one student `$studentId` resolves to, **only** if this parent is
     * legitimately linked to them in the active school — never trusts the id
     * alone. Returns `null` (callers `abort(404)`) for a wrong child, another
     * family's child, or a cross-school id; `Student` is itself
     * `BelongsToSchool`, so a foreign-school id can never match regardless.
     */
    public function authorizedStudent(User $user, int $studentId): ?Student
    {
        return $this->guardianFor($user)?->students()->whereKey($studentId)->first();
    }

    public function canAccessStudent(User $user, int $studentId): bool
    {
        return $this->authorizedStudent($user, $studentId) !== null;
    }
}
