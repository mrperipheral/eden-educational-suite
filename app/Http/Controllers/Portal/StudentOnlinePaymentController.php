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
use App\Support\Portal\StudentPortalAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Online payment for the signed-in student's own fees (M20,
 * `docs/paystack.md`). Resolved through `StudentPortalAuthorizer`, exactly
 * like every other Student Portal controller — a student can only ever pay
 * for, or see the receipt for, their own record.
 */
class StudentOnlinePaymentController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly PaymentInitiationService $initiator,
        private readonly PaymentVerificationService $verifier,
        private readonly FeeStatementBuilder $statements,
        private readonly TenantContext $tenant,
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);
        $settings = $student->school->settings;

        return view('student.fees.pay', [
            'student' => $student,
            'paystackReady' => $settings !== null && $settings->paystackReady(),
            'outstanding' => $this->statements->statementFor($student)['totalOutstanding'],
        ]);
    }

    public function store(InitiatePaymentRequest $request): RedirectResponse
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);

        try {
            $transaction = $this->initiator->initiate(
                $student,
                $request->user(),
                $request->amount(),
                route('student.fees.pay.callback'),
            );
        } catch (PaystackNotConfiguredException|\InvalidArgumentException|PaystackApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($transaction->authorization_url);
    }

    public function callback(Request $request): View|RedirectResponse
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);
        $reference = trim((string) $request->query('reference', ''));

        if ($reference === '') {
            return to_route('student.fees.show')->with('error', __('Missing payment reference.'));
        }

        $activeSchool = $this->tenant->schoolOrFail();

        try {
            $transaction = $this->verifier->verifyAndRecord($reference);
        } catch (UnknownPaystackReferenceException) {
            $this->tenant->set($activeSchool);
            abort(404);
        } catch (PaystackApiException) {
            $this->tenant->set($activeSchool);

            return view('student.fees.pay-pending', ['student' => $student]);
        }

        $this->tenant->set($activeSchool);

        abort_unless($transaction->student_id === $student->id, 404);

        $transaction->loadMissing(['payment.allocations.charge:id,description', 'initiatedBy:id,name,email']);

        return view('student.fees.receipt', [
            'student' => $student,
            'school' => $activeSchool,
            'transaction' => $transaction,
        ]);
    }
}
