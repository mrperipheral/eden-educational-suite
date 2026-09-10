<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\TeacherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\LinkTeacherUserRequest;
use App\Http\Requests\Teacher\TeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherStatusRequest;
use App\Models\Teacher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Teacher professional records. `Teacher` is a `BelongsToSchool` model resolved
 * with tenant-scoped `findOrFail`, so another school's id 404s (the lookup runs
 * after the `tenant` middleware). The whole area is behind `module:staff`
 * **and** `->can('staff.view' | 'staff.manage')`.
 *
 * A teacher record is separate from authentication — `user_id` is optional and
 * set only through {@see self::updateUser()}. `status` changes only through
 * {@see self::updateStatus()}. Teaching assignments are {@see TeacherAssignmentController}.
 */
class TeacherController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $this->authorize('staff.view');

        $status = TeacherStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('q', ''));

        $teachers = Teacher::query()
            ->search($search)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->withCount('activeAssignments')
            ->with('user:id,name')
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('teachers.index', [
            'teachers' => $teachers,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('staff.manage');

        return view('teachers.create', ['teacher' => new Teacher]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $teacher = Teacher::create($request->validated());

        return to_route('teachers.show', $teacher)
            ->with('status', __(':name has been added.', ['name' => $teacher->shortName()]));
    }

    public function show(int $teacher): View
    {
        $this->authorize('staff.view');

        $teacher = Teacher::query()
            ->with([
                'user:id,name,email',
                'assignments' => fn ($q) => $q->with(['session', 'period', 'level', 'arm', 'subject'])->ordered(),
            ])
            ->findOrFail($teacher);

        return view('teachers.show', [
            'teacher' => $teacher,
            'statuses' => TeacherStatus::all(),
            'members' => $this->tenant->schoolOrFail()->users()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    public function edit(int $teacher): View
    {
        $this->authorize('staff.manage');

        return view('teachers.edit', [
            'teacher' => Teacher::query()->findOrFail($teacher),
        ]);
    }

    public function update(TeacherRequest $request, int $teacher): RedirectResponse
    {
        $model = Teacher::query()->findOrFail($teacher);
        $model->update($request->validated());

        return to_route('teachers.show', $model)->with('status', __('Teacher details updated.'));
    }

    public function updateStatus(UpdateTeacherStatusRequest $request, int $teacher): RedirectResponse
    {
        $model = Teacher::query()->findOrFail($teacher);

        // `status` is deliberately not mass-assignable — set it directly.
        $model->status = $request->status();
        $model->save();

        return to_route('teachers.show', $model)
            ->with('status', __('Status set to :status.', ['status' => $request->status()->label()]));
    }

    public function updateUser(LinkTeacherUserRequest $request, int $teacher): RedirectResponse
    {
        $model = Teacher::query()->findOrFail($teacher);

        // `user_id` is deliberately not mass-assignable — set it directly.
        $model->user_id = $request->userId();
        $model->save();

        return to_route('teachers.show', $model)->with('status', $model->user_id
            ? __('Account linked.')
            : __('Account unlinked.'));
    }
}
