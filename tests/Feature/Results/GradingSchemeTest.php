<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\GradingScheme;
use App\Models\GradingSchemeGrade;

class GradingSchemeTest extends ResultsTestCase
{
    public function test_a_scheme_and_its_grade_bands_can_be_created_and_edited(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/grading-schemes', ['name' => 'Custom Scheme'])->assertRedirect();
        $scheme = GradingScheme::query()->withoutGlobalScopes()->where('school_id', $school->id)->where('name', 'Custom Scheme')->firstOrFail();

        $this->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'remark' => 'Excellent',
        ])->assertRedirect();
        $grade = GradingSchemeGrade::query()->withoutGlobalScopes()->where('grading_scheme_id', $scheme->id)->firstOrFail();
        $this->assertSame('A', $grade->code);

        $this->patch("/results/grading-schemes/grades/{$grade->id}", [
            'code' => 'A', 'min_percentage' => 75, 'max_percentage' => 100, 'remark' => 'Outstanding',
        ])->assertRedirect();
        $this->assertSame('75.00', $grade->fresh()->min_percentage);

        $this->patch("/results/grading-schemes/{$scheme->id}", ['name' => 'Renamed Scheme', 'is_active' => '1'])->assertRedirect();
        $this->assertSame('Renamed Scheme', $scheme->fresh()->name);
    }

    public function test_overlapping_active_grade_ranges_are_rejected(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = GradingScheme::factory()->create();
        $scheme->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from("/results/grading-schemes/{$scheme->id}")->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'B', 'min_percentage' => 60, 'max_percentage' => 75,
        ])->assertSessionHasErrors('min_percentage');
    }

    public function test_an_inactive_grade_does_not_block_a_new_overlapping_range(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = GradingScheme::factory()->create();
        $scheme->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'position' => 1, 'is_active' => false]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'B', 'min_percentage' => 60, 'max_percentage' => 100,
        ])->assertSessionHasNoErrors();
    }

    public function test_ranges_must_be_ordered_and_within_bounds(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = GradingScheme::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from("/results/grading-schemes/{$scheme->id}")->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'A', 'min_percentage' => 80, 'max_percentage' => 70,
        ])->assertSessionHasErrors('max_percentage');

        $this->from("/results/grading-schemes/{$scheme->id}")->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'A', 'min_percentage' => -5, 'max_percentage' => 10,
        ])->assertSessionHasErrors('min_percentage');

        $this->from("/results/grading-schemes/{$scheme->id}")->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'A', 'min_percentage' => 90, 'max_percentage' => 105,
        ])->assertSessionHasErrors('max_percentage');
    }

    public function test_grade_codes_are_unique_within_a_scheme(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = GradingScheme::factory()->create();
        $scheme->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from("/results/grading-schemes/{$scheme->id}")->post("/results/grading-schemes/{$scheme->id}/grades", [
            'code' => 'a', 'min_percentage' => 0, 'max_percentage' => 50,
        ])->assertSessionHasErrors('code');
    }

    public function test_percentage_to_grade_calculation(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = GradingScheme::factory()->create();
        $scheme->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'remark' => 'Excellent', 'position' => 1]);
        $scheme->grades()->create(['code' => 'B', 'min_percentage' => 50, 'max_percentage' => 69.99, 'remark' => 'Good', 'position' => 2]);
        $scheme->grades()->create(['code' => 'F', 'min_percentage' => 0, 'max_percentage' => 49.99, 'remark' => 'Fail', 'position' => 3]);

        $this->assertSame('A', $scheme->gradeFor(100)->code);
        $this->assertSame('A', $scheme->gradeFor(70)->code);
        $this->assertSame('B', $scheme->gradeFor(69.99)->code);
        $this->assertSame('B', $scheme->gradeFor(50)->code);
        $this->assertSame('F', $scheme->gradeFor(0)->code);
        $this->assertNull($scheme->gradeFor(-1));
    }

    public function test_grading_schemes_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $scheme = GradingScheme::factory()->create(['name' => 'A-only']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/results/grading-schemes')->assertOk()->assertDontSee('A-only');
        $this->get("/results/grading-schemes/{$scheme->id}")->assertNotFound();
        $this->patch("/results/grading-schemes/{$scheme->id}", ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('A-only', $scheme->fresh()->name);
    }

    public function test_duplicate_scheme_name_within_a_school_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        GradingScheme::factory()->create(['name' => 'Standard']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/grading-schemes')->post('/results/grading-schemes', ['name' => 'Standard'])
            ->assertSessionHasErrors('name');
    }
}
