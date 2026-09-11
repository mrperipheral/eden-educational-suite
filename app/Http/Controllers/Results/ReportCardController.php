<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Models\ReportCardConfiguration;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

/**
 * Renders one student's report card for a result run — the **same** view
 * serves both an authorized "preview before publishing" and the final
 * printable output, per `docs/results-report-cards.md` §"Report card
 * preview": there is no separate preview rendering path to drift out of sync.
 *
 * Field visibility: an **unlocked** run (draft/compiled/reviewed/approved/
 * published) always resolves the *live*, current `ReportCardConfiguration` —
 * a config edit is reflected immediately, publishing included. Only a
 * **locked** run switches to its own frozen snapshot
 * (`ReportCardConfiguration::forRun()`, taken at publish time), so a later
 * configuration change never rewrites a historical report card.
 */
class ReportCardController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function show(int $run, int $student_result): View
    {
        $this->authorize('result.view');

        $run = ResultRun::query()
            ->with(['session', 'period', 'level', 'arm'])
            ->findOrFail($run);

        abort_if($run->isDraft(), 404, __('This run has not been compiled yet.'));

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->with(['student'])
            ->findOrFail($student_result);

        $subjectResults = $studentResult->resultRun->subjectResults()
            ->where('student_id', $studentResult->student_id)
            ->with(['subject', 'components' => fn ($q) => $q->ordered()])
            ->get()
            ->sortBy(fn ($sr) => $sr->subject?->name);

        $configuration = $run->isLocked()
            ? (ReportCardConfiguration::forRun($run) ?? ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id))
            : ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id);

        $signatures = ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id);

        return view('results.report-card.show', [
            'school' => $this->tenant->schoolOrFail()->loadMissing('settings'),
            'run' => $run,
            'studentResult' => $studentResult,
            'subjectResults' => $subjectResults,
            'configuration' => $configuration,
            'signatures' => $signatures,
        ]);
    }
}
