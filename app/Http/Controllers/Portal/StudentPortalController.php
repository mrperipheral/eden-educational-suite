<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Student Portal's landing page (see `docs/student-portal.md`). Gated
 * `module:student-portal` **and** `->can('portal.student')`. The student is
 * resolved through `App\Support\Portal\StudentPortalAuthorizer`, never a raw
 * query the view happens to filter — an unlinked account sees a safe empty
 * state, never an error.
 */
class StudentPortalController extends Controller
{
    public function __construct(private readonly StudentPortalAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])]);

        return view('student.dashboard', ['student' => $student]);
    }
}
