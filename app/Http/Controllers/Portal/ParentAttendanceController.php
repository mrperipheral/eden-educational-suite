<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use App\Support\Results\AttendanceSummarizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A child's attendance summary, per term (see `docs/parent-portal.md`
 * §"Attendance"). Reuses `App\Support\Results\AttendanceSummarizer` (M15) —
 * no attendance data is duplicated or recomputed here; only **submitted**
 * (locked) M13 registers ever count. Internal recording metadata
 * (`recorded_by`, register ids) is never exposed — only the day counts and
 * percentage the summarizer already returns.
 *
 * Degrades gracefully if the Attendance module is off for this school
 * (`docs/parent-portal.md` §"Module activation") — a clean unavailable state,
 * not a broken page.
 */
class ParentAttendanceController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $moduleOn = $this->modules->enabled(Module::Attendance);

        $summaries = collect();

        if ($moduleOn) {
            $sessionIds = $studentModel->enrollments()->pluck('academic_session_id')->unique()->values();

            $periods = AcademicPeriod::query()
                ->whereIn('academic_session_id', $sessionIds)
                ->with('session')
                ->orderByDesc('academic_session_id')
                ->orderByDesc('position')
                ->get();

            $summaries = $periods
                ->map(fn (AcademicPeriod $period) => [
                    'period' => $period,
                    'summary' => AttendanceSummarizer::summarize([$studentModel->id], $period->academic_session_id, $period->id)[$studentModel->id],
                ])
                ->filter(fn (array $row) => $row['summary']['opened'] > 0)
                ->values();
        }

        return view('parent.attendance.index', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'summaries' => $summaries,
            'moduleOn' => $moduleOn,
            'modules' => $this->modules,
        ]);
    }
}
