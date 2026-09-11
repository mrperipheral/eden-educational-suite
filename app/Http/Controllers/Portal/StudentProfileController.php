<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own read-only profile (see
 * `docs/student-portal.md`). Deliberately **read-only** — M9 stays the
 * source of truth; a student who needs a detail corrected contacts the
 * school. Login identity (name/email/password) is the `User` account,
 * edited at the existing `/settings/profile`.
 */
class StudentProfileController extends Controller
{
    public function __construct(private readonly StudentPortalAuthorizer $authorizer) {}

    public function edit(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])]);

        return view('student.profile.edit', ['student' => $student]);
    }
}
