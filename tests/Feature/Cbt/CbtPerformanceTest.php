<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

/**
 * N+1 regression guards for the staff/student CBT listing pages (M23 spec
 * §17 — "no N+1 queries"). Asserts the query count stays flat as the
 * number of examinations grows, not just that the page loads.
 */
class CbtPerformanceTest extends CbtTestCase
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

    public function test_staff_examinations_index_query_count_does_not_grow_with_more_exams(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->examinationIn($school, $context);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get('/cbt/examinations')->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->examinationIn($school, $this->classContext($school));
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get('/cbt/examinations')->assertOk());

        // A small constant tolerance for incidental auth-cache variance
        // between requests — what this guards against is O(n) growth (one
        // extra query per extra exam), not exact equality.
        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the examinations index must not issue more queries as more exams are added');
    }

    public function test_student_cbt_index_query_count_does_not_grow_with_more_exams(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        [$user, $student] = $this->studentUserFor($school, $context);
        $this->examinationIn($school, $context, ['status' => 'scheduled', 'scheduled_at' => now()]);

        $baseline = $this->countQueriesFor(fn () => $this->get('/student/cbt')->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->examinationIn($school, $context, ['status' => 'scheduled', 'scheduled_at' => now()]);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get('/student/cbt')->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the student CBT index must not issue more queries as more exams are added');
    }

    public function test_question_bank_index_query_count_does_not_grow_with_more_questions(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get('/cbt/questions')->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->questionIn($school, $this->classContext($school)['subject']);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get('/cbt/questions')->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the question bank index must not issue more queries as more questions are added');
    }

    public function test_question_bank_index_with_filters_stays_flat_too(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $url = '/cbt/questions?'.http_build_query(['subject' => $context['subject']->id, 'status' => 'active']);

        $baseline = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->questionIn($school, $context['subject']);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'filtered question bank queries must not scale with row count either');
    }
}
