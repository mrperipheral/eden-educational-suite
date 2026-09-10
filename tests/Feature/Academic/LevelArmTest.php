<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\LevelArm;
use App\Models\School;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class LevelArmTest extends AcademicTestCase
{
    private function level(School $school): AcademicLevel
    {
        $this->enterSchool($school);
        $level = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();

        return $level;
    }

    private function arms(int $schoolId)
    {
        return LevelArm::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_add_and_edit_an_arm(): void
    {
        $school = $this->newSchool();
        $level = $this->level($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/academic/levels/{$level->id}/arms", ['name' => 'Gold', 'code' => 'g', 'position' => 1])
            ->assertRedirect(route('academic.levels.show', $level->id));

        $arm = $this->arms($school->id)->firstOrFail();
        $this->assertSame($level->id, $arm->academic_level_id);
        $this->assertSame($school->id, $arm->school_id);
        $this->assertSame('G', $arm->code);

        $this->patch("/academic/arms/{$arm->id}", ['name' => 'Gold House', 'code' => 'G', 'position' => 1, 'is_active' => '0'])
            ->assertRedirect(route('academic.levels.show', $level->id));
        $this->assertFalse((bool) $arm->fresh()->is_active);
    }

    public function test_name_code_and_order_are_unique_within_the_level(): void
    {
        $school = $this->newSchool();
        $level = $this->level($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/academic/levels/{$level->id}/arms", ['name' => 'A', 'code' => 'A', 'position' => 1]);

        $this->from(route('academic.levels.show', $level->id))
            ->post("/academic/levels/{$level->id}/arms", ['name' => 'A', 'code' => 'B', 'position' => 2])->assertSessionHasErrors('name');
        $this->from(route('academic.levels.show', $level->id))
            ->post("/academic/levels/{$level->id}/arms", ['name' => 'B', 'code' => 'A', 'position' => 3])->assertSessionHasErrors('code');
        $this->from(route('academic.levels.show', $level->id))
            ->post("/academic/levels/{$level->id}/arms", ['name' => 'C', 'code' => 'C', 'position' => 1])->assertSessionHasErrors('position');

        $this->assertSame(1, $this->arms($school->id)->count());
    }

    public function test_the_same_arm_name_is_fine_in_a_different_level(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $l1 = AcademicLevel::factory()->create(['name' => 'L1', 'code' => 'L1', 'position' => 1]);
        $l2 = AcademicLevel::factory()->create(['name' => 'L2', 'code' => 'L2', 'position' => 2]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/academic/levels/{$l1->id}/arms", ['name' => 'Blue', 'code' => 'B', 'position' => 1])->assertSessionHasNoErrors();
        $this->post("/academic/levels/{$l2->id}/arms", ['name' => 'Blue', 'code' => 'B', 'position' => 1])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->arms($school->id)->count());
    }

    public function test_authorization_and_module_gate(): void
    {
        $school = $this->newSchool();
        $level = $this->level($school);

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->from(route('academic.levels.show', $level->id))
                ->post("/academic/levels/{$level->id}/arms", ['name' => 'X', 'code' => 'X', 'position' => 1])->assertForbidden();
            $this->flushSession();
        }

        $this->disableAcademics($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/academic/levels/{$level->id}/arms", ['name' => 'X', 'code' => 'X', 'position' => 1])->assertNotFound();
    }

    public function test_arms_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $levelA = $this->level($a);
        $this->enterSchool($a);
        $armA = $levelA->arms()->create(['name' => 'A-arm', 'code' => 'AA', 'position' => 1]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post("/academic/levels/{$levelA->id}/arms", ['name' => 'B-arm', 'code' => 'BB', 'position' => 1])->assertNotFound();
        $this->get("/academic/arms/{$armA->id}/edit")->assertNotFound();
        $this->patch("/academic/arms/{$armA->id}", ['name' => 'x', 'code' => 'x', 'position' => 1])->assertNotFound();

        $this->assertSame(0, $this->arms($b->id)->count());
        $this->assertSame('A-arm', $armA->fresh()->name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $arm = LevelArm::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $arm->school_id = $b->id;
        $arm->save();
    }
}
