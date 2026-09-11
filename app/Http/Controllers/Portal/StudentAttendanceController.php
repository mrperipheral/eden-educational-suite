<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use App\Support\Results\AttendanceSummarizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own attendance summary, per term (see
 * `docs/student-portal.md` §"Attendance"). Reuses
 * `App\Support\Results\AttendanceSummarizer` (M15) — no attendance data is
 * duplicated or recomputed; only **submitted** (locked) M13 registers count.
 * Degrades gracefully if the Attendance module is off.
 */
class StudentAttendanceController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $moduleOn = $this->modules->enabled(Module::Attendance);

        $summaries = collect();

        if ($student && $moduleOn) {
            $sessionIds = $student->enrollments()->pluck('academic_session_id')->unique()->values();

            $periods = AcademicPeriod::query()
                ->whereIn('academic_session_id', $sessionIds)
                ->with('session')
                ->orderByDesc('academic_session_id')
                ->orderByDesc('position')
                ->get();

            $summaries = $periods
                ->map(fn (AcademicPeriod $period) => [
                    'period' => $period,
                    'summary' => AttendanceSummarizer::summarize([$student->id], $period->academic_session_id, $period->id)[$student->id],
                ])
                ->filter(fn (array $row) => $row['summary']['opened'] > 0)
                ->values();
        }

        return view('student.attendance.index', [
            'student' => $student,
            'summaries' => $summaries,
            'moduleOn' => $moduleOn,
        ]);
    }
}
