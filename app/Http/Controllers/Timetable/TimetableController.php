<?php

namespace App\Http\Controllers\Timetable;

use App\Enums\TimetableStatus;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\TimetableRequest;
use App\Http\Requests\Timetable\UpdateTimetableStatusRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Support\Timetable\TimetableConflictScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Timetables. `Timetable` / `TimetableEntry` are `BelongsToSchool` models
 * resolved with tenant-scoped `findOrFail`, so another school's id 404s (the
 * lookup runs after the `tenant` middleware). The whole area is behind
 * `module:timetable` **and** `->can('timetable.view' | 'timetable.manage')`.
 *
 * `status` / `published_at` are never mass-assigned — publishing goes through
 * {@see self::updateStatus()}, which refuses a timetable with any scheduling
 * clash. Lessons are {@see TimetableEntryController}.
 */
class TimetableController extends Controller
{
    public function __construct(private readonly TimetableConflictScanner $scanner) {}

    public function index(Request $request): View
    {
        $this->authorize('timetable.view');

        $status = TimetableStatus::tryFrom((string) $request->query('status'));
        $sessionId = (int) $request->query('session') ?: null;

        $timetables = Timetable::query()
            ->with(['session', 'period'])
            ->withCount('entries')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('timetables.index', [
            'timetables' => $timetables,
            'sessions' => AcademicSession::query()->orderByDesc('starts_on')->get(),
            'status' => $status,
            'sessionId' => $sessionId,
        ]);
    }

    public function create(): View
    {
        $this->authorize('timetable.manage');

        return view('timetables.create', [
            'timetable' => new Timetable,
            ...$this->sessionOptions(),
        ]);
    }

    public function store(TimetableRequest $request): RedirectResponse
    {
        $timetable = Timetable::create($request->validated());

        return to_route('timetables.show', $timetable)
            ->with('status', __('Timetable ":name" created.', ['name' => $timetable->name]));
    }

    public function show(int $timetable, Request $request): View
    {
        $this->authorize('timetable.view');

        $timetable = Timetable::query()->with(['session', 'period'])->findOrFail($timetable);

        $armId = (int) $request->query('arm') ?: null;
        $teacherId = (int) $request->query('teacher') ?: null;
        $weekdayParam = $request->query('weekday');
        $weekday = ($weekdayParam !== null && $weekdayParam !== '')
            ? Weekday::tryFrom((int) $weekdayParam)
            : null;

        $entries = $timetable->entries()
            ->with(['level', 'arm', 'subject', 'teacher'])
            ->when($armId, fn ($q) => $q->where('level_arm_id', $armId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($weekday, fn ($q) => $q->where('weekday', $weekday->value))
            ->ordered()
            ->get();

        $conflicts = $this->scanner->pairs($timetable);

        return view('timetables.show', [
            'timetable' => $timetable,
            'entries' => $entries,
            'conflicts' => $conflicts,
            'conflictIds' => $conflicts->flatMap(fn ($p) => [$p->a_id, $p->b_id])->unique()->values(),
            'weekdaysInUse' => $entries->pluck('weekday')->unique()->sortBy(fn (Weekday $d) => $d->value)->values(),
            'levels' => AcademicLevel::query()->with(['arms' => fn ($q) => $q->ordered()])->ordered()->get(),
            'teachers' => Teacher::query()->ordered()->get(),
            'statuses' => TimetableStatus::all(),
            'filters' => ['arm' => $armId, 'teacher' => $teacherId, 'weekday' => $weekday?->value],
        ]);
    }

    public function edit(int $timetable): View
    {
        $this->authorize('timetable.manage');

        $timetable = Timetable::query()->with('session')->findOrFail($timetable);

        return view('timetables.edit', [
            'timetable' => $timetable,
            ...$this->sessionOptions(),
        ]);
    }

    public function update(TimetableRequest $request, int $timetable): RedirectResponse
    {
        $model = Timetable::query()->findOrFail($timetable);
        $model->update($request->validated());

        return to_route('timetables.show', $model)->with('status', __('Timetable updated.'));
    }

    public function updateStatus(UpdateTimetableStatusRequest $request, int $timetable): RedirectResponse
    {
        $model = Timetable::query()->findOrFail($timetable);

        if ($request->status() === TimetableStatus::Published) {
            $model->publish();
            $message = __('Timetable published.');
        } else {
            $model->unpublish();
            $message = __('Timetable moved back to draft.');
        }

        return to_route('timetables.show', $model)->with('status', $message);
    }

    public function destroy(int $timetable): RedirectResponse
    {
        $this->authorize('timetable.manage');

        $model = Timetable::query()->findOrFail($timetable);

        if ($model->isPublished()) {
            return to_route('timetables.show', $model)
                ->with('error', __('Move the timetable back to draft before deleting it.'));
        }

        $model->delete();

        return to_route('timetables.index')->with('status', __('Timetable deleted.'));
    }

    public function teacherView(Request $request): View
    {
        $this->authorize('timetable.view');

        $teacherId = (int) $request->query('teacher') ?: null;
        $teacher = $teacherId ? Teacher::query()->find($teacherId) : null;

        $entries = $teacher
            ? TimetableEntry::query()
                ->where('teacher_id', $teacher->id)
                ->with(['timetable.session', 'timetable.period', 'level', 'arm', 'subject'])
                ->ordered()
                ->get()
            : collect();

        return view('timetables.teacher', [
            'teachers' => Teacher::query()->ordered()->get(),
            'teacher' => $teacher,
            'entries' => $entries,
            'weekdaysInUse' => $entries->pluck('weekday')->unique()->sortBy(fn (Weekday $d) => $d->value)->values(),
        ]);
    }

    /**
     * @return array{sessions: Collection<int, AcademicSession>}
     */
    private function sessionOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
        ];
    }
}
