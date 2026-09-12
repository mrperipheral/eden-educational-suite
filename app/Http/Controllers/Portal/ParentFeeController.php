<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Fees\FeeStatementBuilder;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A child's fee statement (see `docs/fees.md`). Read-only — a parent can
 * never create a charge, record a payment, or adjust a balance. Reuses
 * `App\Services\Fees\FeeStatementBuilder`, the exact same builder the staff
 * screens and the Student Portal use, so a statement can never drift
 * between audiences.
 */
class ParentFeeController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly FeeStatementBuilder $statements,
        private readonly SchoolModules $modules,
    ) {}

    public function show(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $statement = $this->statements->statementFor($studentModel);
        $settings = $studentModel->school->settings;

        return view('parent.fees.show', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'modules' => $this->modules,
            'payOnlineUrl' => $settings?->paystackReady() ? route('parent.fees.pay.create', $studentModel) : null,
            ...$statement,
        ]);
    }
}
