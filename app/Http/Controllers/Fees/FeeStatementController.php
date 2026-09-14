<?php

namespace App\Http\Controllers\Fees;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Services\Fees\FeeStatementBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The fee management dashboard (`fees.view`/`fees.report`) and a single
 * student's fee statement (`fees.view`) — the same
 * `App\Services\Fees\FeeStatementBuilder` the Parent/Student portals use, so
 * a statement can never drift between audiences. `{student}` is resolved by
 * tenant-scoped `findOrFail`, so another school's id 404s.
 */
class FeeStatementController extends Controller
{
    public function __construct(private readonly FeeStatementBuilder $statements) {}

    public function index(Request $request): View
    {
        $this->authorize('fees.report');

        $search = $request->query('search');

        $students = Student::query()
            ->whereHas('feeCharges')
            ->when($search, fn ($q, $term) => $q->search($term))
            ->with(['feeCharges.allocations.payment:id,voided_at'])
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $rows = $students->getCollection()->map(fn (Student $student) => [
            'student' => $student,
            'outstanding' => $student->feeCharges->reduce(
                fn (string $carry, StudentFeeCharge $c) => bcadd($carry, $c->outstandingBalance(), 2),
                '0.00',
            ),
        ]);

        return view('fees.dashboard', [
            'rows' => $rows,
            'students' => $students,
            'search' => $search,
            'totalCharged' => (string) (StudentFeeCharge::query()->selectRaw('COALESCE(SUM(amount), 0) as total')->value('total') ?? '0.00'),
            'totalDiscounted' => (string) (StudentFeeCharge::query()->selectRaw('COALESCE(SUM(discount_amount), 0) as total')->value('total') ?? '0.00'),
            'totalCollected' => (string) (FeePayment::query()->notVoided()->selectRaw('COALESCE(SUM(amount), 0) as total')->value('total') ?? '0.00'),
            // M29.5 — a small, fixed-size (5) recent-activity list for the
            // dashboard; not a report, just orientation for the person
            // opening this screen for the day.
            'recentPayments' => FeePayment::query()
                ->notVoided()
                ->with('student:id,first_name,middle_name,last_name')
                ->ordered()
                ->limit(5)
                ->get(),
        ]);
    }

    public function show(int $student): View
    {
        $this->authorize('fees.view');

        $student = Student::query()->findOrFail($student);
        $statement = $this->statements->statementFor($student);

        return view('fees.statement', [
            'student' => $student,
            'canManage' => request()->user()->hasPermission(Permission::FeesManage),
            'canRecordPayment' => request()->user()->hasPermission(Permission::FeesRecordPayment),
            'canAdjust' => request()->user()->hasPermission(Permission::FeesAdjust),
            ...$statement,
        ]);
    }
}
