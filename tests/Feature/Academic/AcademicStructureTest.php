<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * The relationships the later Student / Teacher / Timetable / Assessment modules
 * will build on, and the tenant-safety of the one cross-model link M8 ships
 * (level ↔ subject).
 */
class AcademicStructureTest extends AcademicTestCase
{
    public function test_school_owns_its_sessions_levels_and_subjects(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);

        AcademicSession::factory()->count(2)->create();
        AcademicLevel::factory()->count(3)->create();
        Subject::factory()->count(4)->create();

        $this->assertSame(2, $school->academicSessions()->count());
        $this->assertSame(3, $school->academicLevels()->count());
        $this->assertSame(4, $school->subjects()->count());
    }

    public function test_a_session_has_ordered_periods_and_a_current_period(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $session = AcademicSession::factory()->create();

        $session->periods()->create(['name' => 'T2', 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-01', 'position' => 2]);
        $first = $session->periods()->create(['name' => 'T1', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1]);
        $first->makeCurrent();

        $this->assertSame(['T1', 'T2'], $session->periods()->ordered()->pluck('name')->all());
        $this->assertTrue($session->currentPeriod->is($first));
    }

    public function test_a_level_offers_subjects_through_a_synced_link(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);

        $level = AcademicLevel::factory()->create();
        $maths = Subject::factory()->create(['name' => 'Maths', 'code' => 'MTH']);
        $english = Subject::factory()->create(['name' => 'English', 'code' => 'ENG']);
        $french = Subject::factory()->create(['name' => 'French', 'code' => 'FRE']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->put("/academic/levels/{$level->id}/subjects", ['subjects' => [$maths->id, $english->id]])
            ->assertRedirect(route('academic.levels.show', $level->id));

        $this->assertEqualsCanonicalizing([$maths->id, $english->id], $level->fresh()->subjects->pluck('id')->all());
        $this->assertDatabaseHas('level_subject', ['school_id' => $school->id, 'academic_level_id' => $level->id, 'subject_id' => $maths->id]);

        // Re-sync replaces the set.
        $this->put("/academic/levels/{$level->id}/subjects", ['subjects' => [$french->id]]);
        $this->assertSame([$french->id], $level->fresh()->subjects->pluck('id')->all());

        // Clear it.
        $this->put("/academic/levels/{$level->id}/subjects", ['subjects' => []]);
        $this->assertCount(0, $level->fresh()->subjects);
    }

    public function test_a_level_cannot_be_linked_to_another_schools_subject(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $subjectA = Subject::factory()->create(['name' => 'A-only', 'code' => 'AONLY']);
        $this->app->forgetScopedInstances();

        $this->enterSchool($b);
        $levelB = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->from(route('academic.levels.show', $levelB->id))
            ->put("/academic/levels/{$levelB->id}/subjects", ['subjects' => [$subjectA->id]])
            ->assertSessionHasErrors('subjects.0');

        $this->assertSame(0, DB::table('level_subject')->count());
    }

    public function test_subject_sync_reads_stay_scoped_to_the_active_school(): void
    {
        // The belongsToMany read applies the Subject scope, so School B never
        // sees School A's level-subject links.
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $levelA = AcademicLevel::factory()->create();
        $subjectA = Subject::factory()->create();
        $levelA->subjects()->sync([$subjectA->id => ['school_id' => $a->id]]);
        $this->assertCount(1, $levelA->fresh()->subjects, 'the link is visible inside School A');
        $this->app->forgetScopedInstances();

        $this->enterSchool($b);
        $levelB = AcademicLevel::factory()->create();

        $this->assertCount(0, $levelB->subjects);
        $this->assertSame(0, Subject::query()->whereHas('levels')->count(), 'School B sees no linked subjects');
    }

    public function test_view_roles_see_the_structure_but_cannot_sync_subjects(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $level = AcademicLevel::factory()->create();
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/academic/levels/{$level->id}")->assertOk();
            $this->from(route('academic.levels.show', $level->id))
                ->put("/academic/levels/{$level->id}/subjects", ['subjects' => [$subject->id]])
                ->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(0, DB::table('level_subject')->count());
    }
}
