<?php

namespace App\Http\Controllers\Reports\Concerns;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * The academic-structure filter dropdowns (session/period/level/arm/
 * subject) every M27 report shares — the exact same eager-loaded query
 * shape `ResultRunController`/`ExaminationController` already use for
 * their own filter forms, extracted once here so it isn't retyped in every
 * report controller. Filters stay server-side: these are display options
 * only, never trusted for authorization — each report class re-validates
 * the submitted ids itself (tenant-scoped, teacher-scoped where relevant).
 */
trait BuildsReportFilterOptions
{
    /**
     * @return array{sessions: Collection, levels: Collection, subjects: Collection}
     */
    private function academicFilterOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            'subjects' => Subject::query()->ordered()->get(),
        ];
    }
}
