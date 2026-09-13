<?php

namespace App\Reports;

use App\Enums\StudentStatus;
use App\Models\PromotionBatch;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Promotion & Graduation (M21) reporting — promotion batch history and
 * graduation history, both read directly from the existing
 * `PromotionBatch`/`PromotionRecord` rows and the `students.status`/
 * `graduated_*` columns. No placement-recommendation logic of any kind —
 * this only reports what already happened, per `docs/reporting.md` §10.
 */
class PromotionReport
{
    /**
     * @param  array{session?:int}  $filters
     */
    public function batches(array $filters): LengthAwarePaginator
    {
        return $this->batchesQuery($filters)->paginate(15)->withQueryString();
    }

    /**
     * @param  array{session?:int}  $filters
     * @return Builder<PromotionBatch>
     */
    public function batchesQuery(array $filters): Builder
    {
        $query = PromotionBatch::query()
            ->with([
                'sourceSession:id,name', 'sourceLevel:id,name', 'sourceArm:id,name',
                'targetSession:id,name', 'targetLevel:id,name', 'targetArm:id,name',
                'createdBy:id,name',
            ])
            ->withCount('records')
            ->ordered();

        if (! empty($filters['session'])) {
            $query->where(fn (Builder $q) => $q->where('source_academic_session_id', $filters['session'])
                ->orWhere('target_academic_session_id', $filters['session']));
        }

        return $query;
    }

    /**
     * @param  array{session?:int}  $filters
     */
    public function graduationHistory(array $filters): LengthAwarePaginator
    {
        return $this->graduationHistoryQuery($filters)->paginate(25)->withQueryString();
    }

    /**
     * @param  array{session?:int}  $filters
     * @return Builder<Student>
     */
    public function graduationHistoryQuery(array $filters): Builder
    {
        $query = Student::query()
            ->where('status', StudentStatus::Graduated->value)
            ->with(['graduatedSession:id,name', 'graduatedBy:id,name'])
            ->orderByDesc('graduated_at');

        if (! empty($filters['session'])) {
            $query->where('graduated_academic_session_id', $filters['session']);
        }

        return $query;
    }

    public function graduatedCount(): int
    {
        return Student::query()->where('status', StudentStatus::Graduated->value)->count();
    }
}
