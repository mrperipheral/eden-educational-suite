<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\AssessmentCategory;
use App\Models\ResultWeightingScheme;

class WeightingSchemeTest extends ResultsTestCase
{
    public function test_a_scheme_and_its_category_weights_can_be_created_and_edited(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $category = AssessmentCategory::factory()->create(['name' => 'Test']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/weighting-schemes', ['name' => 'Custom Weighting'])->assertRedirect();
        $scheme = ResultWeightingScheme::query()->withoutGlobalScopes()->where('school_id', $school->id)->where('name', 'Custom Weighting')->firstOrFail();

        $this->post("/results/weighting-schemes/{$scheme->id}/items", [
            'assessment_category_id' => $category->id, 'weight_percentage' => 100,
        ])->assertRedirect();
        $this->assertSame(1, $scheme->items()->count());
        $this->assertSame(100.0, $scheme->totalWeight());

        $item = $scheme->items()->first();
        $this->patch("/results/weighting-schemes/items/{$item->id}", [
            'assessment_category_id' => $category->id, 'weight_percentage' => 80,
        ])->assertRedirect();
        $this->assertSame('80.00', $item->fresh()->weight_percentage);
    }

    public function test_a_category_can_only_be_weighted_once_per_scheme(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = ResultWeightingScheme::factory()->create();
        $category = AssessmentCategory::factory()->create();
        $scheme->items()->create(['assessment_category_id' => $category->id, 'weight_percentage' => 50]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from("/results/weighting-schemes/{$scheme->id}")->post("/results/weighting-schemes/{$scheme->id}/items", [
            'assessment_category_id' => $category->id, 'weight_percentage' => 50,
        ])->assertSessionHasErrors('assessment_category_id');
    }

    public function test_an_item_can_be_removed(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $scheme = ResultWeightingScheme::factory()->create();
        $item = $scheme->items()->create(['assessment_category_id' => AssessmentCategory::factory()->create()->id, 'weight_percentage' => 100]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/results/weighting-schemes/items/{$item->id}")->assertRedirect();
        $this->assertNull($item->fresh());
    }

    public function test_weighting_schemes_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $scheme = ResultWeightingScheme::factory()->create(['name' => 'A-only']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/results/weighting-schemes')->assertOk()->assertDontSee('A-only');
        $this->get("/results/weighting-schemes/{$scheme->id}")->assertNotFound();
        $this->patch("/results/weighting-schemes/{$scheme->id}", ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('A-only', $scheme->fresh()->name);
    }

    public function test_a_cross_school_category_cannot_be_weighted(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $scheme = ResultWeightingScheme::factory()->create();
        $this->app->forgetScopedInstances();
        $this->enterSchool($b);
        $foreignCategory = AssessmentCategory::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->from("/results/weighting-schemes/{$scheme->id}")->post("/results/weighting-schemes/{$scheme->id}/items", [
            'assessment_category_id' => $foreignCategory->id, 'weight_percentage' => 100,
        ])->assertSessionHasErrors('assessment_category_id');
    }

    public function test_duplicate_scheme_name_within_a_school_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        ResultWeightingScheme::factory()->create(['name' => 'Standard']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/weighting-schemes')->post('/results/weighting-schemes', ['name' => 'Standard'])
            ->assertSessionHasErrors('name');
    }
}
