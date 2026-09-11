<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Models\Timetable;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A child's current class timetable (see `docs/parent-portal.md`
 * §"Timetable"). Only ever the **published** M12 timetable for the child's
 * *current* enrollment (session + arm) — a draft timetable is never shown.
 * Degrades to a clean unavailable state (not a broken page) when the
 * Timetable module is off, the child has no current enrollment, or no
 * timetable has been published yet for their session.
 */
class ParentTimetableController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $studentModel->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['level', 'arm'])]);
        $moduleOn = $this->modules->enabled(Module::Timetable);

        $timetable = null;
        $entriesByDay = collect();

        $enrollment = $studentModel->currentEnrollment;

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

        return view('parent.timetable.index', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'timetable' => $timetable,
            'entriesByDay' => $entriesByDay,
            'weekdays' => Weekday::all(),
            'moduleOn' => $moduleOn,
            'modules' => $this->modules,
        ]);
    }
}
