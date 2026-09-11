<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\ChargeRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Services\Fees\FeeChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Raise a charge against a student — either from an active
 * {@see FeeStructure} or entered manually. Gated `fees.manage`, behind
 * `module:fees`. `{student}` is resolved by tenant-scoped `findOrFail`, so
 * another school's id 404s.
 */
class FeeChargeController extends Controller
{
    public function __construct(private readonly FeeChargeService $charges) {}

    public function create(int $student): View
    {
        $this->authorize('fees.manage');

        $student = Student::query()->with('currentEnrollment')->findOrFail($student);

        return view('fees.charges.create', [
            'student' => $student,
            'structures' => FeeStructure::query()
                ->active()
                ->when(
                    $student->currentEnrollment,
                    fn ($q, $enrollment) => $q->applicableTo($enrollment->academic_level_id, $enrollment->level_arm_id),
                )
                ->with(['category:id,name', 'session:id,name', 'period:id,name'])
                ->ordered()
                ->get(),
            'categories' => FeeCategory::query()->active()->ordered()->get(),
            'sessions' => AcademicSession::query()->orderByDesc('starts_on')->get(),
            'periods' => AcademicPeriod::query()->orderBy('position')->get(),
        ]);
    }

    public function store(ChargeRequest $request, int $student): RedirectResponse
    {
        $studentModel = Student::query()->findOrFail($student);

        try {
            if ($request->usesStructure()) {
                $structure = FeeStructure::query()->active()->findOrFail($request->input('fee_structure_id'));
                $this->charges->createFromStructure($studentModel, $structure, $request->user(), $request->description());
            } else {
                $this->charges->createManual($studentModel, $request->manualPayload(), $request->user());
            }
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return to_route('fees.students.show', $studentModel)->with('status', __('Charge added.'));
    }
}
