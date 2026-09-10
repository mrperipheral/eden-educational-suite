<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Requests\Attendance\RegisterRequest;
use App\Http\Requests\Attendance\SubmitRegisterRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use App\Support\Attendance\AttendanceAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Attendance registers. `AttendanceRegister` / `AttendanceRecord` are
 * `BelongsToSchool` models resolved with tenant-scoped `findOrFail`, so another
 * school's id 404s (the lookup runs after the `tenant` middleware). The whole
 * area is behind `module:attendance` **and** `->can('attendance.view' |
 * 'attendance.record' | 'attendance.manage')`.
 *
 * Creating a register snapshots one `AttendanceRecord` per currently-eligible
 * student (a single bulk insert). Marks start `null`; a register cannot be
 * submitted while any record is unmarked. Once submitted a register is locked —
 * `reopen()` (`attendance.manage`) is the only way to change it.
 */
class AttendanceRegisterController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AttendanceAuthorizer $authorizer,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('attendance.view');

        $filters = [
            'date' => $request->query('date') ?: null,
            'session' => (int) $request->query('session') ?: null,
            'level' => (int) $request->query('level') ?: null,
            'arm' => (int) $request->query('arm') ?: null,
            'status' => AttendanceRegisterStatus::tryFrom((string) $request->query('status')),
        ];

        $registers = AttendanceRegister::query()
            ->with(['session', 'period', 'level', 'arm'])
            ->withCount([
                'records',
                'records as present_count' => fn ($q) => $q->whereIn('status', [
                    AttendanceStatus::Present->value, AttendanceStatus::Late->value,
                ]),
            ])
            ->when($filters['date'], fn ($q, $d) => $q->whereDate('attendance_date', $d))
            ->when($filters['session'], fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($filters['level'], fn ($q, $id) => $q->where('academic_level_id', $id))
            ->when($filters['arm'], fn ($q, $id) => $q->where('level_arm_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s->value))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('attendance.index', [
            'registers' => $registers,
            'filters' => $filters,
            ...$this->classOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('attendance.record');

        return view('attendance.create', [
            'register' => new AttendanceRegister(['attendance_date' => now()->toDateString()]),
            ...$this->classOptions(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $register = DB::transaction(function () use ($request) {
            $register = AttendanceRegister::create($request->validated());

            $rows = $register->eligibleStudents()->pluck('id')->map(fn ($studentId) => [
                'school_id' => $this->tenant->idOrFail(),
                'attendance_register_id' => $register->id,
                'student_id' => $studentId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows !== []) {
                AttendanceRecord::query()->insert($rows);
            }

            return $register;
        });

        return to_route('attendance.show', $register)
            ->with('status', __('Register created — mark the students below.'));
    }

    public function show(int $register): View
    {
        $this->authorize('attendance.view');

        $register = AttendanceRegister::query()
            ->with(['session', 'period', 'level', 'arm', 'submittedBy:id,name'])
            ->findOrFail($register);

        $register->load(['records' => fn ($q) => $q->with([
            'student:id,first_name,middle_name,last_name,preferred_name,admission_number,status',
        ])]);

        $records = $register->records
            ->sortBy(fn (AttendanceRecord $r) => [$r->student?->last_name, $r->student?->first_name])
            ->values();

        return view('attendance.show', [
            'register' => $register,
            'records' => $records,
            'summary' => $register->summary(),
            'statuses' => AttendanceStatus::all(),
            'canRecord' => $this->authorizer->canRecordForClass(
                request()->user(), $register->academic_level_id, $register->level_arm_id,
            ),
            'canManage' => request()->user()->hasPermission(Permission::AttendanceManage),
        ]);
    }

    public function updateRecords(RecordAttendanceRequest $request, int $register): RedirectResponse
    {
        $register = AttendanceRegister::query()->findOrFail($register);
        $userId = $request->user()->getKey();
        $existing = $register->records()->get()->keyBy('student_id');

        DB::transaction(function () use ($request, $existing, $userId) {
            foreach ($request->records() as $studentId => $data) {
                $record = $existing->get($studentId);

                if ($record === null) {
                    continue;
                }

                $record->status = $data['status'];
                $record->note = $data['note'];

                if ($record->isDirty(['status', 'note'])) {
                    $record->recorded_by = $userId;
                    $record->recorded_at = now();
                    $record->save();
                }
            }
        });

        if ($request->boolean('submit')) {
            if ($register->records()->exists() && ! $register->records()->unmarked()->exists()) {
                $register->submit($request->user());

                return to_route('attendance.show', $register)
                    ->with('status', __('Attendance saved and register submitted.'));
            }

            return to_route('attendance.show', $register)
                ->with('error', __('Mark every student before submitting.'));
        }

        return to_route('attendance.show', $register)->with('status', __('Attendance saved.'));
    }

    public function submit(SubmitRegisterRequest $request, int $register): RedirectResponse
    {
        $register = AttendanceRegister::query()->findOrFail($register);
        $register->submit($request->user());

        return to_route('attendance.show', $register)->with('status', __('Register submitted and locked.'));
    }

    public function reopen(int $register): RedirectResponse
    {
        $this->authorize('attendance.manage');

        $register = AttendanceRegister::query()->findOrFail($register);

        if (! $register->isLocked()) {
            return to_route('attendance.show', $register)->with('error', __('That register is not submitted.'));
        }

        $register->reopen();

        return to_route('attendance.show', $register)->with('status', __('Register reopened for correction.'));
    }

    public function destroy(Request $request, int $register): RedirectResponse
    {
        $this->authorize('attendance.record');

        $register = AttendanceRegister::query()->findOrFail($register);

        if ($register->isLocked()) {
            return to_route('attendance.show', $register)
                ->with('error', __('Reopen the register before deleting it.'));
        }

        abort_unless(
            $this->authorizer->canRecordForClass($request->user(), $register->academic_level_id, $register->level_arm_id),
            403,
        );

        $register->delete();

        return to_route('attendance.index')->with('status', __('Register deleted.'));
    }

    /**
     * Session (with periods) and level (with arms) options for the filter bar
     * and the create form. Tenant-scoped like everything else.
     *
     * @return array{sessions: Collection<int, AcademicSession>, levels: Collection<int, AcademicLevel>}
     */
    private function classOptions(): array
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
