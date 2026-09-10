<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\TimetableEntryRequest;
use App\Models\AcademicLevel;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Lessons within a timetable. `Timetable` and `TimetableEntry` are both
 * `BelongsToSchool`, resolved with tenant-scoped `findOrFail`. Every scheduling
 * rule (overlap, subject↔level, an active teacher assignment) lives in
 * {@see TimetableEntryRequest}. Gated `timetable.manage` + `module:timetable`.
 */
class TimetableEntryController extends Controller
{
    public function create(int $timetable): View
    {
        $this->authorize('timetable.manage');

        $timetable = Timetable::query()->with(['session', 'period'])->findOrFail($timetable);

        return view('timetables.entries.create', [
            'timetable' => $timetable,
            'entry' => new TimetableEntry,
            ...$this->options(),
        ]);
    }

    public function store(TimetableEntryRequest $request, int $timetable): RedirectResponse
    {
        $timetable = Timetable::query()->findOrFail($timetable);

        $timetable->entries()->create($request->validated());

        return to_route('timetables.show', $timetable)->with('status', __('Lesson added.'));
    }

    public function edit(int $entry): View
    {
        $this->authorize('timetable.manage');

        $entry = TimetableEntry::query()->with(['timetable.session', 'timetable.period'])->findOrFail($entry);

        return view('timetables.entries.edit', [
            'timetable' => $entry->timetable,
            'entry' => $entry,
            ...$this->options(),
        ]);
    }

    public function update(TimetableEntryRequest $request, int $entry): RedirectResponse
    {
        $entry = TimetableEntry::query()->findOrFail($entry);

        $entry->update($request->validated());

        return to_route('timetables.show', $entry->timetable_id)->with('status', __('Lesson updated.'));
    }

    public function destroy(int $entry): RedirectResponse
    {
        $this->authorize('timetable.manage');

        $entry = TimetableEntry::query()->findOrFail($entry);
        $timetableId = $entry->timetable_id;
        $entry->delete();

        return to_route('timetables.show', $timetableId)->with('status', __('Lesson removed.'));
    }

    /**
     * Level (with arms), subject and teacher options for the lesson form.
     * Tenant-scoped like everything else.
     *
     * @return array{levels: Collection<int, AcademicLevel>, subjects: Collection<int, Subject>, teachers: Collection<int, Teacher>}
     */
    private function options(): array
    {
        return [
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            'subjects' => Subject::query()->ordered()->get(),
            'teachers' => Teacher::query()->ordered()->get(),
        ];
    }
}
