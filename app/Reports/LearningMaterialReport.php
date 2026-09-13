<?php

namespace App\Reports;

use App\Models\LearningMaterial;
use Illuminate\Support\Collection;

/**
 * Learning Materials (M22) reporting — a lightweight administrative
 * summary only (count, by subject, by type, recent uploads). M22 has no
 * view/download analytics to report on (deferred per
 * `docs/learning-materials.md`), so this never attempts to show "who
 * viewed this" — see `docs/reporting.md` §11.
 */
class LearningMaterialReport
{
    /**
     * @return array{total:int, by_subject: Collection, by_type: array<string,int>, recent: Collection}
     */
    public function summary(): array
    {
        $bySubject = LearningMaterial::query()
            ->selectRaw('subject_id, COUNT(*) as total')
            ->groupBy('subject_id')
            ->with('subject:id,name')
            ->get();

        // ->toBase(): type is enum-cast on the model, and an enum instance
        // can't be a PHP array key.
        $byType = LearningMaterial::query()->selectRaw('type, COUNT(*) as total')->groupBy('type')->toBase()->pluck('total', 'type')->all();

        $recent = LearningMaterial::query()
            ->with(['subject:id,name', 'level:id,name', 'arm:id,name', 'uploadedBy:id,name'])
            ->ordered()
            ->limit(10)
            ->get();

        return [
            'total' => LearningMaterial::query()->count(),
            'by_subject' => $bySubject,
            'by_type' => $byType,
            'recent' => $recent,
        ];
    }
}
