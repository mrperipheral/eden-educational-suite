<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Services\Results\ReportCardRenderer;
use Illuminate\View\View;

/**
 * Renders one student's report card for a result run — the **same** view
 * serves both an authorized "preview before publishing" and the final
 * printable output, per `docs/results-report-cards.md` §"Report card
 * preview": there is no separate preview rendering path to drift out of sync.
 * The rendering itself is `App\Services\Results\ReportCardRenderer`, shared
 * with the Parent Portal (M16, `docs/parent-portal.md`) — this controller
 * only resolves *which* run/student a school/staff viewer may ask for.
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
    public function __construct(private readonly ReportCardRenderer $renderer) {}

    public function show(int $run, int $student_result): View
    {
        $this->authorize('result.view');

        $run = ResultRun::query()->findOrFail($run);

        abort_if($run->isDraft(), 404, __('This run has not been compiled yet.'));

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->findOrFail($student_result);

        return $this->renderer->render($run, $studentResult);
    }
}
