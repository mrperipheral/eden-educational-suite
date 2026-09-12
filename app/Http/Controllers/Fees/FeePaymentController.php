<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\PaymentRequest;
use App\Http\Requests\Fees\ReasonRequest;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\Fees\FeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Record a manual payment (never Paystack — M20) and, in the same
 * transaction, allocate it across one or more of the student's outstanding
 * charges. Gated `fees.record-payment`, behind `module:fees`. `{student}` /
 * `{payment}` are resolved by tenant-scoped `findOrFail`, so another
 * school's id 404s.
 */
class FeePaymentController extends Controller
{
    public function __construct(private readonly FeePaymentService $payments) {}

    public function create(int $student): View
    {
        $this->authorize('fees.record-payment');

        $student = Student::query()->findOrFail($student);

        $outstanding = $student->feeCharges()
            ->with(['category:id,name', 'allocations.payment:id,voided_at'])
            ->ordered()
            ->get()
            ->filter(fn ($c) => ! $c->isFullyPaid());

        return view('fees.payments.create', [
            'student' => $student,
            'outstandingCharges' => $outstanding,
            'methods' => array_values(array_filter(PaymentMethod::all(), fn (PaymentMethod $m) => $m !== PaymentMethod::Paystack)),
        ]);
    }

    public function store(PaymentRequest $request, int $student): RedirectResponse
    {
        $studentModel = Student::query()->findOrFail($student);

        try {
            $this->payments->record($studentModel, $request->payload(), $request->allocations(), $request->user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return to_route('fees.students.show', $studentModel)->with('status', __('Payment recorded.'));
    }

    public function void(ReasonRequest $request, int $payment): RedirectResponse
    {
        $payment = FeePayment::query()->findOrFail($payment);
        $payment->void($request->user(), $request->reason());

        return to_route('fees.students.show', $payment->student_id)->with('status', __('Payment voided.'));
    }
}
