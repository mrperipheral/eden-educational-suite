<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherAssignmentRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A teacher's teaching assignments. `Teacher` and `TeacherAssignment` are both
 * `BelongsToSchool`, resolved with tenant-scoped `findOrFail`. The academic ids
 * in the payload are validated to belong to the active school by
 * {@see TeacherAssignmentRequest}. Gated `staff.manage` + `module:staff`.
 *
 * An assignment that ends is marked `ended` (via edit) and the row is kept as
 * history; `destroy` is a hard delete for a mis-entered row.
 */
class TeacherAssignmentController extends Controller
{
    public function create(int $teacher): View
    {
        $this->authorize('staff.manage');

        $teacher = Teacher::query()->findOrFail($teacher);

        return view('teachers.assignments.create', [
            'teacher' => $teacher,
            'assignment' => new TeacherAssignment,
            ...$this->options(),
        ]);
    }

    public function store(TeacherAssignmentRequest $request, int $teacher): RedirectResponse
    {
        $teacher = Teacher::query()->findOrFail($teacher);

        $teacher->assignments()->create($request->validated());

        return to_route('teachers.show', $teacher)->with('status', __('Assignment added.'));
    }

    public function edit(int $assignment): View
    {
        $this->authorize('staff.manage');

        $assignment = TeacherAssignment::query()->with('teacher')->findOrFail($assignment);

        return view('teachers.assignments.edit', [
            'teacher' => $assignment->teacher,
            'assignment' => $assignment,
            ...$this->options(),
        ]);
    }

    public function update(TeacherAssignmentRequest $request, int $assignment): RedirectResponse
    {
        $assignment = TeacherAssignment::query()->findOrFail($assignment);

        $assignment->update($request->validated());

        return to_route('teachers.show', $assignment->teacher_id)->with('status', __('Assignment updated.'));
    }

    public function destroy(int $assignment): RedirectResponse
    {
        $this->authorize('staff.manage');

        $assignment = TeacherAssignment::query()->findOrFail($assignment);
        $teacherId = $assignment->teacher_id;
        $assignment->delete();

        return to_route('teachers.show', $teacherId)->with('status', __('Assignment removed.'));
    }

    /**
     * Session (with periods), level (with arms) and subject options for the
     * assignment form. Tenant-scoped like everything else.
     *
     * @return array{sessions: Collection<int, AcademicSession>, levels: Collection<int, AcademicLevel>, subjects: Collection<int, Subject>}
     */
    private function options(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            'subjects' => Subject::query()->ordered()->get(),
        ];
    }
}
