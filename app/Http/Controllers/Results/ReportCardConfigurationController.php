<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\ReportCardConfigurationRequest;
use App\Http\Requests\Results\SignatureUploadRequest;
use App\Models\AcademicSession;
use App\Models\ReportCardConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The school's report-card field configuration (see
 * `docs/results-report-cards.md` §"Report card configuration"). Gated
 * `result.view` (read) / `result.manage` (write), behind `module:results`.
 *
 * A configuration is resolved/edited for a **scope** — `?session=` and
 * `?period=` query params, both optional — via
 * `App\Models\ReportCardConfiguration::forScope()`. Saving always
 * find-or-updates the one row for that exact scope (never creates a
 * duplicate) — see the model.
 */
class ReportCardConfigurationController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('result.view');

        $sessionId = (int) $request->query('session') ?: null;
        $periodId = (int) $request->query('period') ?: null;

        return view('results.report-card-configuration.edit', [
            'configuration' => ReportCardConfiguration::forScope($sessionId, $periodId),
            'sessionId' => $sessionId,
            'periodId' => $periodId,
            'sessions' => AcademicSession::query()->with(['periods' => fn ($q) => $q->ordered()])->orderByDesc('starts_on')->get(),
        ]);
    }

    public function update(ReportCardConfigurationRequest $request): RedirectResponse
    {
        $data = $request->payload();

        $configuration = ReportCardConfiguration::exactScopeRow($data['academic_session_id'], $data['academic_period_id']);
        $configuration->fill($data);
        $configuration->save();

        return to_route('results.report-card-configuration.edit', [
            'session' => $data['academic_session_id'], 'period' => $data['academic_period_id'],
        ])->with('status', __('Report card configuration saved.'));
    }

    public function updatePrincipalSignature(SignatureUploadRequest $request): RedirectResponse
    {
        return $this->uploadSignature($request, 'putPrincipalSignature');
    }

    public function updateClassTeacherSignature(SignatureUploadRequest $request): RedirectResponse
    {
        return $this->uploadSignature($request, 'putClassTeacherSignature');
    }

    public function destroyPrincipalSignature(Request $request): RedirectResponse
    {
        return $this->removeSignature($request, 'clearPrincipalSignature');
    }

    public function destroyClassTeacherSignature(Request $request): RedirectResponse
    {
        return $this->removeSignature($request, 'clearClassTeacherSignature');
    }

    public function showPrincipalSignature(Request $request): StreamedResponse|Response
    {
        return $this->streamSignature($request, 'principal_signature_path', 'hasPrincipalSignature');
    }

    public function showClassTeacherSignature(Request $request): StreamedResponse|Response
    {
        return $this->streamSignature($request, 'class_teacher_signature_path', 'hasClassTeacherSignature');
    }

    private function uploadSignature(SignatureUploadRequest $request, string $setter): RedirectResponse
    {
        $configuration = $this->scopeConfiguration($request);

        $path = $request->file('signature')->store(
            'report-card-signatures/'.$configuration->school_id,
            ReportCardConfiguration::SIGNATURE_DISK,
        );

        $configuration->{$setter}($path);

        return to_route('results.report-card-configuration.edit', [
            'session' => $configuration->academic_session_id, 'period' => $configuration->academic_period_id,
        ])->with('status', __('Signature uploaded.'));
    }

    private function removeSignature(Request $request, string $clearer): RedirectResponse
    {
        $this->authorize('result.manage');

        $configuration = $this->scopeConfiguration($request);
        $configuration->{$clearer}();

        return to_route('results.report-card-configuration.edit', [
            'session' => $configuration->academic_session_id, 'period' => $configuration->academic_period_id,
        ])->with('status', __('Signature removed.'));
    }

    private function streamSignature(Request $request, string $column, string $hasMethod): StreamedResponse|Response
    {
        $this->authorize('result.view');

        $configuration = $this->scopeConfiguration($request);
        abort_unless($configuration->{$hasMethod}(), 404);

        return Storage::disk(ReportCardConfiguration::SIGNATURE_DISK)
            ->response($configuration->{$column}, 'signature', ['Cache-Control' => 'private, max-age=300']);
    }

    /** Resolve the exact-scope configuration row for `?session=&period=`, creating one if none exists yet. */
    private function scopeConfiguration(Request $request): ReportCardConfiguration
    {
        $sessionId = (int) $request->query('session') ?: null;
        $periodId = (int) $request->query('period') ?: null;

        $configuration = ReportCardConfiguration::exactScopeRow($sessionId, $periodId);

        if (! $configuration->exists) {
            $configuration->save();
        }

        return $configuration;
    }
}
