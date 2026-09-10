<?php

namespace App\Http\Controllers\Assessment\Concerns;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\AssessmentCategory;
use Illuminate\Support\Collection;

/**
 * The tenant-scoped academic option lists the assessment / assignment create
 * forms and filter bars need. Eager-loaded so the Alpine cascade in the Blade
 * view renders without an N+1.
 */
trait ProvidesAcademicOptions
{
    /**
     * @return array{
     *     sessions: Collection<int, AcademicSession>,
     *     levels: Collection<int, AcademicLevel>,
     *     categories: Collection<int, AssessmentCategory>
     * }
     */
    protected function academicOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with([
                    'arms' => fn ($q) => $q->ordered(),
                    'subjects' => fn ($q) => $q->ordered(),
                ])
                ->ordered()
                ->get(),
            'categories' => AssessmentCategory::query()->ordered()->get(),
        ];
    }
}
