<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class AcademicLevelTest extends AcademicTestCase
{
    private function levels(int $schoolId)
    {
        return AcademicLevel::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_create_and_edit_a_level(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/levels', ['name' => 'Primary 1', 'code' => 'pri1', 'position' => 1])
            ->assertRedirect(route('academic.levels.index'));

        $level = $this->levels($school->id)->firstOrFail();
        $this->assertSame('Primary 1', $level->name);
        $this->assertSame('PRI1', $level->code, 'code is upper-cased');
        $this->assertTrue((bool) $level->is_active);

        $this->patch("/academic/levels/{$level->id}", ['name' => 'Primary One', 'code' => 'PRI1', 'position' => 1, 'is_active' => '0'])
            ->assertRedirect(route('academic.levels.index'));
        $this->assertSame('Primary One', $level->fresh()->name);
        $this->assertFalse((bool) $level->fresh()->is_active);
    }

    public function test_name_code_and_position_are_unique_per_school(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/levels', ['name' => 'JSS 1', 'code' => 'JSS1', 'position' => 1]);

        $this->from('/academic/levels')->post('/academic/levels', ['name' => 'JSS 1', 'code' => 'X', 'position' => 2])->assertSessionHasErrors('name');
        $this->from('/academic/levels')->post('/academic/levels', ['name' => 'Y', 'code' => 'JSS1', 'position' => 3])->assertSessionHasErrors('code');
        $this->from('/academic/levels')->post('/academic/levels', ['name' => 'Z', 'code' => 'Z', 'position' => 1])->assertSessionHasErrors('position');

        $this->assertSame(1, $this->levels($school->id)->count());
    }

    public function test_the_same_level_name_is_fine_in_another_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post('/academic/levels', ['name' => 'Grade 1', 'code' => 'G1', 'position' => 1])->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post('/academic/levels', ['name' => 'Grade 1', 'code' => 'G1', 'position' => 1])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->levels($a->id)->count());
        $this->assertSame(1, $this->levels($b->id)->count());
    }

    public function test_editing_ignores_the_levels_own_values(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $level = AcademicLevel::factory()->create(['name' => 'JSS 1', 'code' => 'JSS1', 'position' => 3]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/academic/levels/{$level->id}", ['name' => 'JSS 1', 'code' => 'JSS1', 'position' => 3])
            ->assertSessionHasNoErrors();
    }

    public function test_code_rejects_odd_characters(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/academic/levels')->post('/academic/levels', ['name' => 'X', 'code' => 'a/b*c', 'position' => 1])
            ->assertSessionHasErrors('code');
    }

    public function test_authorization(): void
    {
        $school = $this->newSchool();

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/levels')->assertOk();
            $this->from('/academic/levels')->post('/academic/levels', ['name' => "L-{$role->value}", 'code' => 'L', 'position' => 1])->assertForbidden();
            $this->flushSession();
        }

        foreach ($this->academicRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/levels')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_module_gate(): void
    {
        $school = $this->newSchool();
        $this->disableAcademics($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/academic/levels')->assertNotFound();
    }

    public function test_levels_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $levelA = AcademicLevel::factory()->create(['name' => 'A-Level']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/academic/levels')->assertOk()->assertDontSee('A-Level');
        $this->get("/academic/levels/{$levelA->id}")->assertNotFound();
        $this->get("/academic/levels/{$levelA->id}/edit")->assertNotFound();
        $this->patch("/academic/levels/{$levelA->id}", ['name' => 'x', 'code' => 'x', 'position' => 1])->assertNotFound();

        $this->assertSame('A-Level', $levelA->fresh()->name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $level = AcademicLevel::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $level->school_id = $b->id;
        $level->save();
    }
}
