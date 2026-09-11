<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AssignmentStatus;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own class assignments (see
 * `docs/student-portal.md` §"Assignments"). Reads the M14
 * `AssignmentSubmission` snapshotted for this student — the query is always
 * `student_id = $student->id`, so another student's submission can never
 * appear. Only `published`/`closed` assignments are shown
 * ({@see AssignmentStatus::visibleToParents()} — shared with the Parent
 * Portal, M16). **View only** — M14 has no online submission workflow yet;
 * building one is out of scope here (see `docs/student-portal.md`
 * §"Deferred").
 */
class StudentAssignmentController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $moduleOn = $this->modules->enabled(Module::Assessments);

        /** @var LengthAwarePaginator<int, AssignmentSubmission>|null $submissions */
        $submissions = ($student && $moduleOn)
            ? AssignmentSubmission::query()
                ->where('assignment_submissions.student_id', $student->id)
                ->whereHas('assignment', fn ($q) => $q->whereIn('status', $this->visibleStatuses()))
                ->with(['assignment' => fn ($q) => $q->with(['subject', 'teacher'])])
                ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
                ->orderByDesc('assignments.due_on')
                ->select('assignment_submissions.*')
                ->paginate(15)
                ->withQueryString()
            : null;

        return view('student.assignments.index', [
            'student' => $student,
            'submissions' => $submissions,
            'moduleOn' => $moduleOn,
        ]);
    }

    /** @return list<string> */
    private function visibleStatuses(): array
    {
        return array_map(
            fn (AssignmentStatus $s) => $s->value,
            array_values(array_filter(AssignmentStatus::all(), fn ($s) => $s->visibleToParents())),
        );
    }
}
