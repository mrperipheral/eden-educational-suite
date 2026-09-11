<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AssignmentStatus;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A child's class assignments (see `docs/parent-portal.md` §"Assignments").
 * Reads the M14 `AssignmentSubmission` snapshotted for this student — never
 * another student's submission, since the query is always scoped to
 * `student_id = $studentModel->id`. Only assignments whose status is
 * {@see AssignmentStatus::visibleToParents()} (`published` / `closed`) are
 * shown — a `draft` assignment is not yet real set work. Teacher-owned
 * records stay read-only here; nothing on this page can modify them.
 */
class ParentAssignmentController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $moduleOn = $this->modules->enabled(Module::Assessments);

        /** @var LengthAwarePaginator<int, AssignmentSubmission>|null $submissions */
        $submissions = $moduleOn
            ? AssignmentSubmission::query()
                ->where('assignment_submissions.student_id', $studentModel->id)
                ->whereHas('assignment', fn ($q) => $q->whereIn('status', $this->visibleStatuses()))
                ->with(['assignment' => fn ($q) => $q->with(['subject', 'teacher'])])
                ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
                ->orderByDesc('assignments.due_on')
                ->select('assignment_submissions.*')
                ->paginate(15)
                ->withQueryString()
            : null;

        return view('parent.assignments.index', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'submissions' => $submissions,
            'moduleOn' => $moduleOn,
            'modules' => $this->modules,
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
