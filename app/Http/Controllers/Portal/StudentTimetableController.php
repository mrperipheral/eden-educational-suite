<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Models\Timetable;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own current class timetable (see
 * `docs/student-portal.md` §"Timetable"). Only the **published** M12
 * timetable for the student's *current* enrollment (session + arm) — a
 * draft timetable is never shown. Degrades cleanly when unavailable.
 */
class StudentTimetableController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['level', 'arm'])]);
        $moduleOn = $this->modules->enabled(Module::Timetable);

        $timetable = null;
        $entriesByDay = collect();

        $enrollment = $student?->currentEnrollment;

        if ($moduleOn && $enrollment !== null) {
            $timetable = Timetable::query()
                ->published()
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->orderByDesc('id')
                ->first();

            if ($timetable !== null) {
                $entriesByDay = $timetable->entries()
                    ->where('level_arm_id', $enrollment->level_arm_id)
                    ->with(['subject', 'teacher'])
                    ->ordered()
                    ->get()
                    ->groupBy(fn ($entry) => $entry->weekday->value);
            }
        }

        return view('student.timetable.index', [
            'student' => $student,
            'timetable' => $timetable,
            'entriesByDay' => $entriesByDay,
            'weekdays' => Weekday::all(),
            'moduleOn' => $moduleOn,
        ]);
    }
}
