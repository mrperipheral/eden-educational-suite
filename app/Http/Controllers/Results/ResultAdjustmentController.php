<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\ResultAdjustmentRequest;
use App\Models\ResultAdjustment;
use App\Services\Results\ResultCompiler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The controlled result-adjustment workflow (see
 * `docs/results-report-cards.md` §"Score correction"). Gated `result.adjust`,
 * behind `module:results`. A proposal has no effect until explicitly applied
 * — applying recomputes the whole run's ranking (a changed score can move
 * everyone's position).
 */
class ResultAdjustmentController extends Controller
{
    public function __construct(private readonly ResultCompiler $compiler) {}

    public function store(ResultAdjustmentRequest $request, int $run, int $subject_result): RedirectResponse
    {
        $subjectResult = $request->subjectResult();

        $adjustment = $subjectResult->adjustments()->make($request->payload());
        $adjustment->requested_by = $request->user()->getKey();
        $adjustment->requested_at = now();
        $adjustment->save();

        return to_route('results.runs.show', $run)->with('status', __('Adjustment proposed — apply it to take effect.'));
    }

    public function apply(Request $request, int $run, int $adjustment): RedirectResponse
    {
        $this->authorize('result.adjust');

        $adjustment = ResultAdjustment::query()->findOrFail($adjustment);
        abort_unless($adjustment->subjectResult->result_run_id === $run, 404);

        if (! $adjustment->isPending()) {
            return to_route('results.runs.show', $run)->with('error', __('That adjustment has already been decided.'));
        }

        $adjustment->apply($request->user());
        $this->compiler->recomputeRanking($adjustment->subjectResult->resultRun);

        return to_route('results.runs.show', $run)->with('status', __('Adjustment applied — positions recomputed.'));
    }

    public function reject(Request $request, int $run, int $adjustment): RedirectResponse
    {
        $this->authorize('result.adjust');

        $adjustment = ResultAdjustment::query()->findOrFail($adjustment);
        abort_unless($adjustment->subjectResult->result_run_id === $run, 404);

        if (! $adjustment->isPending()) {
            return to_route('results.runs.show', $run)->with('error', __('That adjustment has already been decided.'));
        }

        $adjustment->reject($request->user());

        return to_route('results.runs.show', $run)->with('status', __('Adjustment rejected.'));
    }
}
