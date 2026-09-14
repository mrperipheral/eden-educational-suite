<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])]);

        return view('student.dashboard', [
            'student' => $student,
            'todayClasses' => $this->todayClasses($student),
        ]);
    }

    /**
     * M29.5 — this student's own lessons for today, from their current
     * enrollment's level/arm on a *published* timetable only. Reuses the
     * same Timetable module data the Student Portal's own Timetable tab
     * already renders — no new metric.
     *
     * @return Collection<int, TimetableEntry>|null
     */
    private function todayClasses(?Student $student): ?Collection
    {
        if ($student === null || ! $this->modules->enabled(Module::Timetable) || ! $student->currentEnrollment) {
            return null;
        }

        $enrollment = $student->currentEnrollment;
        $today = now($student->school->settings?->timezone ?? config('app.timezone'))->dayOfWeek;

        return TimetableEntry::query()
            ->where('academic_level_id', $enrollment->academic_level_id)
            ->when($enrollment->level_arm_id, fn ($q) => $q->where('level_arm_id', $enrollment->level_arm_id))
            ->where('weekday', $today)
            ->whereHas('timetable', fn ($q) => $q->published())
            ->with(['subject:id,name', 'teacher:id,first_name,middle_name,last_name'])
            ->orderBy('start_time')
            ->limit(8)
            ->get();
    }
}
