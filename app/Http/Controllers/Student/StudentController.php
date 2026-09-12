<?php

namespace App\Http\Controllers\Student;

use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\LinkStudentUserRequest;
use App\Http\Requests\Student\StudentRequest;
use App\Http\Requests\Student\UpdateStudentStatusRequest;
use App\Models\Student;
use App\Services\Audit\AuditRecorder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student records. `Student` is a `BelongsToSchool` model resolved with
 * tenant-scoped `findOrFail`, so another school's id 404s (the lookup runs
 * after the `tenant` middleware). The whole area is behind `module:students`
 * **and** `->can('student.view' | 'student.manage')`.
 *
 * The current class is never stored on the student — it is the one `active`
 * enrollment (see `docs/student-management.md`).
 */
class StudentController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditRecorder $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('student.view');

        $status = StudentStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q', ''));

        $students = Student::query()
            ->search($search)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->with(['currentEnrollment' => fn ($q) => $q->with(['level', 'arm'])])
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('student.manage');

        return view('students.create', ['student' => new Student]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = Student::create($request->validated());

        $this->audit->record(
            event: 'student.created',
            summary: __(':actor added student :name.', ['actor' => $request->user()->name, 'name' => $student->shortName()]),
            auditable: $student,
            auditableLabel: $student->shortName(),
        );

        return to_route('students.show', $student)
            ->with('status', __(':name has been added.', ['name' => $student->shortName()]));
    }

    public function show(int $student): View
    {
        $this->authorize('student.view');

        $student = Student::query()
            ->with([
                'enrollments' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])->ordered(),
                'guardianLinks' => fn ($q) => $q->with('guardian'),
                'user:id,name,email',
            ])
            ->findOrFail($student);

        return view('students.show', [
            'student' => $student,
            'statuses' => StudentStatus::all(),
            'relationships' => GuardianRelationship::all(),
            'members' => $this->tenant->schoolOrFail()->users()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    public function edit(int $student): View
    {
        $this->authorize('student.manage');

        return view('students.edit', [
            'student' => Student::query()->findOrFail($student),
        ]);
    }

    public function update(StudentRequest $request, int $student): RedirectResponse
    {
        $model = Student::query()->findOrFail($student);
        $model->update($request->validated());

        return to_route('students.show', $model)->with('status', __('Student details updated.'));
    }

    public function updateStatus(UpdateStudentStatusRequest $request, int $student): RedirectResponse
    {
        $model = Student::query()->findOrFail($student);
        $previousStatus = $model->status;

        // `status` is deliberately not mass-assignable — set it directly.
        $model->status = $request->status();
        $model->save();

        $this->audit->record(
            event: 'student.status_changed',
            summary: __(':actor changed :name\'s status from :from to :to.', [
                'actor' => $request->user()->name, 'name' => $model->shortName(),
                'from' => $previousStatus->label(), 'to' => $request->status()->label(),
            ]),
            auditable: $model,
            auditableLabel: $model->shortName(),
            before: ['status' => $previousStatus->value],
            after: ['status' => $request->status()->value],
        );

        return to_route('students.show', $model)
            ->with('status', __('Status set to :status.', ['status' => $request->status()->label()]));
    }

    public function updateUser(LinkStudentUserRequest $request, int $student): RedirectResponse
    {
        $model = Student::query()->findOrFail($student);

        // `user_id` is deliberately not mass-assignable — set it directly.
        $model->user_id = $request->userId();
        $model->save();

        return to_route('students.show', $model)->with('status', $model->user_id
            ? __('Account linked.')
            : __('Account unlinked.'));
    }
}
