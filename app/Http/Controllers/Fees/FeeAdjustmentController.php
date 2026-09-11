<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\DiscountRequest;
use App\Http\Requests\Fees\ReasonRequest;
use App\Models\StudentFeeCharge;
use Illuminate\Http\RedirectResponse;

/**
 * Discount / waive / unwaive a charge — `fees.adjust` (narrower than
 * `fees.manage`: a financial correction, not routine configuration).
 * `{charge}` is resolved by tenant-scoped `findOrFail`, so another school's
 * id 404s. Never a raw edit of `amount` — see `App\Models\StudentFeeCharge`.
 */
class FeeAdjustmentController extends Controller
{
    public function discount(DiscountRequest $request, int $charge): RedirectResponse
    {
        $charge = StudentFeeCharge::query()->findOrFail($charge);

        try {
            $charge->applyDiscount($request->user(), $request->amount(), $request->reason());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('fees.students.show', $charge->student_id)->with('status', __('Discount applied.'));
    }

    public function waive(ReasonRequest $request, int $charge): RedirectResponse
    {
        $charge = StudentFeeCharge::query()->findOrFail($charge);
        $charge->waive($request->user(), $request->reason());

        return to_route('fees.students.show', $charge->student_id)->with('status', __('Charge waived.'));
    }

    public function unwaive(int $charge): RedirectResponse
    {
        $this->authorize('fees.adjust');

        $charge = StudentFeeCharge::query()->findOrFail($charge);
        $charge->unwaive();

        return to_route('fees.students.show', $charge->student_id)->with('status', __('Waiver reversed.'));
    }
}
