<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Fees\FeeStatementBuilder;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own fee statement (see `docs/fees.md`). Read-only
 * — a student can never create a charge, record a payment, or adjust a
 * balance. Reuses `App\Services\Fees\FeeStatementBuilder`, the exact same
 * builder the staff screens and the Parent Portal use.
 */
class StudentFeeController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly FeeStatementBuilder $statements,
        private readonly SchoolModules $modules,
    ) {}

    public function show(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);
        $statement = $this->statements->statementFor($student);
        $settings = $student->school->settings;

        return view('student.fees.show', [
            'student' => $student,
            'modules' => $this->modules,
            'payOnlineUrl' => $settings?->paystackReady() ? route('student.fees.pay.create') : null,
            ...$statement,
        ]);
    }
}
