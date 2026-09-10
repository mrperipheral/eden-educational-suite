<?php

namespace Tests\Feature\Assessment;

use App\Enums\Role;
use App\Models\AssessmentCategory;

class AssessmentCategoryTest extends AssessmentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return AssessmentCategory::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_a_category_can_be_created_and_edited(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/assessments/categories', ['name' => 'Homework', 'code' => 'hw', 'position' => 2])
            ->assertRedirect(route('assessments.categories.index'));

        $category = $this->rowsFor($school->id)->where('name', 'Homework')->firstOrFail();
        $this->assertSame('HW', $category->code, 'code is upper-cased');
        $this->assertTrue($category->is_active);

        $this->patch("/assessments/categories/{$category->id}", ['name' => 'Home Learning', 'is_active' => '0'])
            ->assertRedirect();
        $this->assertSame('Home Learning', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_duplicate_name_or_code_within_a_school_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->enterSchool($school);
        AssessmentCategory::query()->create(['name' => 'Test', 'code' => 'TEST']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.categories.index'))
            ->post('/assessments/categories', ['name' => 'Test'])
            ->assertSessionHasErrors('name');
        $this->from(route('assessments.categories.index'))
            ->post('/assessments/categories', ['name' => 'Different', 'code' => 'TEST'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, $this->rowsFor($school->id)->whereIn('name', ['Test', 'Different'])->count());
    }

    public function test_the_same_category_name_is_allowed_in_a_different_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->scaffold($a);
        $this->scaffold($b);

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post('/assessments/categories', ['name' => 'Exam'])->assertSessionHasNoErrors();
        $this->flushSession();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post('/assessments/categories', ['name' => 'Exam'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rowsFor($a->id)->where('name', 'Exam')->count());
        $this->assertSame(1, $this->rowsFor($b->id)->where('name', 'Exam')->count());
    }

    public function test_categories_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->scaffold($a);
        $this->scaffold($b);
        $this->enterSchool($a);
        $categoryA = AssessmentCategory::query()->create(['name' => 'A-only']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/assessments/categories')->assertOk()->assertDontSee('A-only');
        $this->patch("/assessments/categories/{$categoryA->id}", ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('A-only', $categoryA->fresh()->name);
    }

    public function test_an_invalid_code_format_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.categories.index'))
            ->post('/assessments/categories', ['name' => 'Weird', 'code' => 'a/b!'])
            ->assertSessionHasErrors('code');
    }
}
