<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

/**
 * N+1 regression guards for the Entry / Placement Assessment list and
 * export (M25 spec — "no N+1"). Asserts the query count stays flat as the
 * number of records grows, not just that the page loads.
 */
class EntryAssessmentPerformanceTest extends EntryAssessmentTestCase
{
    private function countQueriesFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    public function test_index_query_count_does_not_grow_with_more_records(): void
    {
        $school = $this->newSchool();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get('/entry-assessments')->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->assessmentIn($school, $context);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get('/entry-assessments')->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the index must not issue more queries as more records are added');
    }

    public function test_index_with_filters_stays_flat_too(): void
    {
        $school = $this->newSchool();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $url = '/entry-assessments?'.http_build_query(['subject' => $context['subject']->id, 'status' => 'active']);

        $baseline = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->assessmentIn($school, $context);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'filtered queries must not scale with row count either');
    }
}
