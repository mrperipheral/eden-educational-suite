<?php

namespace Tests\Feature\Portal;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Performance guarantees for the Parent Portal (see `docs/parent-portal.md`
 * §"Performance"): every child is resolved through the tenant-scoped
 * Guardian ↔ Student relationship (never "load everything then filter in
 * Blade"), and the dashboard / child pages stay query-bounded regardless of
 * how many *other* students or guardians the school has.
 */
class ParentPortalStructureTest extends ParentPortalTestCase
{
    public function test_the_dashboard_query_count_does_not_grow_with_unrelated_school_size(): void
    {
        $small = $this->newSchool();
        [$userSmall] = $this->parentWithChildren($small, 3);

        $large = $this->newSchool();
        [$userLarge] = $this->parentWithChildren($large, 3);
        // A much bigger school around the second parent's own family.
        $this->enterSchool($large);
        Student::factory()->count(40)->create();
        Guardian::factory()->count(15)->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($small, $userSmall);
        DB::enableQueryLog();
        $this->get('/parent')->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->flushSession();

        $this->actingAsParent($large, $userLarge);
        $this->get('/parent')->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $smallCount, $largeCount,
            'the dashboard should issue the same query count regardless of how many other students/guardians the school has.',
        );
    }

    public function test_the_dashboard_query_count_stays_bounded_as_the_parents_own_children_grow(): void
    {
        $oneChild = $this->newSchool();
        [$userOne] = $this->parentWithChildren($oneChild, 1);

        $manyChildren = $this->newSchool();
        [$userMany] = $this->parentWithChildren($manyChildren, 5);

        $this->actingAsParent($oneChild, $userOne);
        DB::enableQueryLog();
        $this->get('/parent')->assertOk();
        $oneCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->flushSession();

        $this->actingAsParent($manyChildren, $userMany);
        $this->get('/parent')->assertOk();
        $manyCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $oneCount, $manyCount,
            'the dashboard eager-loads every child in one query, regardless of how many children the parent has.',
        );
    }

    public function test_the_child_profile_page_query_count_does_not_grow_with_unrelated_school_size(): void
    {
        $small = $this->newSchool();
        [$userSmall, , $studentsSmall] = $this->parentWithChildren($small, 1);

        $large = $this->newSchool();
        [$userLarge, , $studentsLarge] = $this->parentWithChildren($large, 1);
        $this->enterSchool($large);
        Student::factory()->count(40)->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($small, $userSmall);
        DB::enableQueryLog();
        $this->get("/parent/children/{$studentsSmall[0]->id}")->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->flushSession();

        $this->actingAsParent($large, $userLarge);
        $this->get("/parent/children/{$studentsLarge[0]->id}")->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($smallCount, $largeCount);
    }

    public function test_the_authorizer_never_loads_the_full_student_table(): void
    {
        // authorizedStudent() resolves a single row via a tenant-scoped
        // guardian->students() query — never "load all students, filter in
        // PHP". A single, indexed query proves the shape.
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        Student::factory()->count(50)->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        DB::enableQueryLog();
        $this->get("/parent/children/{$students[0]->id}")->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $studentQueries = array_filter($queries, fn ($q) => str_contains($q['query'], '"students"') || str_contains($q['query'], 'students'));
        $this->assertLessThan(5, count($studentQueries), 'resolving one authorized student should not scan the whole table.');
    }
}
