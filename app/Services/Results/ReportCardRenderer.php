<?php

namespace App\Services\Results;

use App\Models\ReportCardConfiguration;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders one student's report card — the **single** place that happens.
 * Used by both `App\Http\Controllers\Results\ReportCardController` (the
 * school/staff side, M15) and `App\Http\Controllers\Portal\
 * ParentReportCardController` (the Parent Portal, M16): the two differ only
 * in *authorization and resolution* — who may ask for which run/student —
 * never in how the report card itself is built, per
 * `docs/results-report-cards.md` §"Report card preview" /
 * `docs/parent-portal.md` §"Report card reuse".
 */
final class ReportCardRenderer
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * A student's per-subject breakdown within a run — the same query backs
     * both the report card and the Parent Portal's plain "Results" page.
     *
     * @return Collection<int, StudentSubjectResult>
     */
    public function subjectResultsFor(ResultRun $run, int $studentId): Collection
    {
        return StudentSubjectResult::query()
            ->where('result_run_id', $run->id)
            ->where('student_id', $studentId)
            ->with(['subject', 'components' => fn ($q) => $q->ordered()])
            ->get()
            ->sortBy(fn ($sr) => $sr->subject?->name);
    }

    public function render(ResultRun $run, StudentResult $studentResult): View
    {
        $run->loadMissing(['session', 'period', 'level', 'arm']);

        $subjectResults = $this->subjectResultsFor($run, $studentResult->student_id);

        $configuration = $run->isLocked()
            ? (ReportCardConfiguration::forRun($run) ?? ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id))
            : ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id);

        $signatures = ReportCardConfiguration::forScope($run->academic_session_id, $run->academic_period_id);

        return view('results.report-card.show', [
            'school' => $this->tenant->schoolOrFail()->loadMissing('settings'),
            'run' => $run,
            'studentResult' => $studentResult->loadMissing('student'),
            'subjectResults' => $subjectResults,
            'configuration' => $configuration,
            'signatures' => $signatures,
        ]);
    }
}
