<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\EnrollmentRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A student's enrollment history. `Student` and `Enrollment` are both
 * `BelongsToSchool`, resolved with tenant-scoped `findOrFail`. The academic ids
 * in the payload are validated to belong to the active school by
 * {@see EnrollmentRequest}. Gated `student.manage` + `module:students`.
 *
 * Creating an "active" enrollment closes any previous one (one class at a time)
 * — this is not a promotion workflow.
 */
class EnrollmentController extends Controller
{
    public function create(int $student): View
    {
        $this->authorize('student.manage');

        $student = Student::query()->findOrFail($student);

        return view('students.enrollments.create', [
            'student' => $student,
            'enrollment' => new Enrollment,
            ...$this->options(),
        ]);
    }

    public function store(EnrollmentRequest $request, int $student): RedirectResponse
    {
        $student = Student::query()->findOrFail($student);

        $enrollment = $student->enrollments()->create(
            collect($request->validated())->except('make_active')->all()
        );

        if ($request->shouldMakeActive() && $enrollment->status === EnrollmentStatus::Active) {
            $enrollment->makeActive();
        }

        return to_route('students.show', $student)
            ->with('status', __('Enrollment added.'));
    }

    public function edit(int $enrollment): View
    {
        $this->authorize('student.manage');

        $enrollment = Enrollment::query()->with('student')->findOrFail($enrollment);

        return view('students.enrollments.edit', [
            'student' => $enrollment->student,
            'enrollment' => $enrollment,
            ...$this->options(),
        ]);
    }

    public function update(EnrollmentRequest $request, int $enrollment): RedirectResponse
    {
        $enrollment = Enrollment::query()->findOrFail($enrollment);

        $enrollment->update(
            collect($request->validated())->except('make_active')->all()
        );

        if ($enrollment->status === EnrollmentStatus::Active) {
            $enrollment->makeActive();
        }

        return to_route('students.show', $enrollment->student_id)
            ->with('status', __('Enrollment updated.'));
    }

    /**
     * Session (with periods) and level (with arms) options for the enrollment
     * form. Tenant-scoped like everything else.
     *
     * @return array{sessions: Collection<int, AcademicSession>, levels: Collection<int, AcademicLevel>}
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
        ];
    }
}
