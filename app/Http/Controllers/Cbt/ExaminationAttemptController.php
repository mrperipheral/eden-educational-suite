<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Examination;
use Illuminate\View\View;

/**
 * A simple, staff-facing list of student attempts/results for one
 * examination (M23, `docs/cbt.md` §13). No analytics beyond a plain list —
 * deferred to a future milestone. Gated `cbt.view`; visibility of a
 * result to *staff* is unconditional (staff always see the mark) —
 * `App\Enums\ResultReleaseMode` only ever governs a *student's* own view
 * of their own result.
 */
class ExaminationAttemptController extends Controller
{
    public function index(int $examination): View
    {
        $this->authorize('cbt.view');

        $examination = Examination::query()->findOrFail($examination);

        return view('cbt.examinations.attempts.index', [
            'examination' => $examination,
            'attempts' => $examination->attempts()
                ->with('student:id,first_name,last_name,preferred_name,admission_number')
                ->ordered()
                ->paginate(20),
        ]);
    }
}
