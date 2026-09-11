<?php

namespace Tests\Feature\Portal;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Performance guarantees for the Student Portal (see
 * `docs/student-portal.md` §"Performance"): the dashboard/profile stay
 * query-bounded regardless of how many *other* students the school has, and
 * `StudentPortalAuthorizer` never scans the whole student table.
 */
class StudentPortalStructureTest extends StudentPortalTestCase
{
    public function test_the_dashboard_query_count_does_not_grow_with_unrelated_school_size(): void
    {
        $small = $this->newSchool();
        [$userSmall] = $this->studentWithAccount($small);

        $large = $this->newSchool();
        [$userLarge] = $this->studentWithAccount($large);
        $this->enterSchool($large);
        Student::factory()->count(50)->create();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($small, $userSmall);
        DB::enableQueryLog();
        $this->get('/student')->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->flushSession();

        $this->actingAsStudentUser($large, $userLarge);
        $this->get('/student')->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $smallCount, $largeCount,
            'the dashboard should issue the same query count regardless of how many other students the school has.',
        );
    }

    public function test_the_authorizer_never_scans_the_full_student_table(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        Student::factory()->count(50)->create();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        DB::enableQueryLog();
        $this->get('/student/profile')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $studentQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'students'));
        $this->assertLessThan(5, count($studentQueries), 'resolving the linked student should not scan the whole table.');
        $this->assertNotNull($student);
    }
}
