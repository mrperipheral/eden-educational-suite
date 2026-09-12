<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\InitiatePaymentRequest;
use App\Services\Fees\FeeStatementBuilder;
use App\Services\Paystack\PaymentInitiationService;
use App\Services\Paystack\PaymentVerificationService;
use App\Services\Paystack\PaystackApiException;
use App\Services\Paystack\PaystackNotConfiguredException;
use App\Services\Paystack\UnknownPaystackReferenceException;
use App\Support\Portal\ParentPortalAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Online payment for a linked child (M20, `docs/paystack.md`). Every
 * `{student}` is resolved through `ParentPortalAuthorizer::
 * authorizedStudent()`, exactly like every other Parent Portal controller —
 * never trusted from the URL alone. A parent can only ever pay for, or see
 * the receipt for, their own linked children.
 */
class ParentOnlinePaymentController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly PaymentInitiationService $initiator,
        private readonly PaymentVerificationService $verifier,
        private readonly FeeStatementBuilder $statements,
        private readonly TenantContext $tenant,
    ) {}

    public function create(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $settings = $studentModel->school->settings;

        return view('parent.fees.pay', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'paystackReady' => $settings !== null && $settings->paystackReady(),
            'outstanding' => $this->statements->statementFor($studentModel)['totalOutstanding'],
        ]);
    }

    public function store(InitiatePaymentRequest $request, int $student): RedirectResponse
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);

        try {
            $transaction = $this->initiator->initiate(
                $studentModel,
                $request->user(),
                $request->amount(),
                route('parent.fees.pay.callback', $studentModel),
            );
        } catch (PaystackNotConfiguredException|\InvalidArgumentException|PaystackApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($transaction->authorization_url);
    }

    public function callback(Request $request, int $student): View|RedirectResponse
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $reference = trim((string) $request->query('reference', ''));

        if ($reference === '') {
            return to_route('parent.fees.show', $studentModel)->with('error', __('Missing payment reference.'));
        }

        // Verification re-anchors the tenant context to the reference's own
        // school while it runs; restore ours immediately afterwards so the
        // rest of this response is never rendered under the wrong tenant —
        // regardless of what the reference actually resolved to.
        $activeSchool = $this->tenant->schoolOrFail();

        try {
            $transaction = $this->verifier->verifyAndRecord($reference);
        } catch (UnknownPaystackReferenceException) {
            $this->tenant->set($activeSchool);
            abort(404);
        } catch (PaystackApiException) {
            $this->tenant->set($activeSchool);

            return view('parent.fees.pay-pending', ['student' => $studentModel]);
        }

        $this->tenant->set($activeSchool);

        // Student ids are never reused across schools, so this alone is a
        // reliable "does this reference actually belong to this child"
        // check — a manipulated {student} paired with someone else's
        // reference can never render their receipt here.
        abort_unless($transaction->student_id === $studentModel->id, 404);

        $transaction->loadMissing(['payment.allocations.charge:id,description', 'initiatedBy:id,name,email']);

        return view('parent.fees.receipt', [
            'student' => $studentModel,
            'school' => $activeSchool,
            'transaction' => $transaction,
        ]);
    }
}
