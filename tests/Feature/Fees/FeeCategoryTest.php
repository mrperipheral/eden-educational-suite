<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeeCategory;

class FeeCategoryTest extends FeesTestCase
{
    public function test_bursar_can_create_and_edit_a_category(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.categories.store'), [
            'name' => 'Tuition', 'code' => 'TUI', 'position' => 1,
        ])->assertRedirect(route('fees.categories.index'));

        $category = FeeCategory::first();
        $this->assertSame('Tuition', $category->name);
        $this->assertTrue($category->is_active);

        $this->patch(route('fees.categories.update', $category), [
            'name' => 'Tuition Fee', 'code' => 'TUI', 'position' => 1, 'is_active' => '0',
        ])->assertRedirect(route('fees.categories.index'));

        $category->refresh();
        $this->assertSame('Tuition Fee', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_name_and_code_are_unique_within_a_school_only(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolA);
        FeeCategory::factory()->create(['name' => 'Tuition', 'code' => 'TUI']);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->post(route('fees.categories.store'), ['name' => 'Tuition', 'code' => 'TUI2'])
            ->assertSessionHasErrors('name');

        $this->actingAsRole($schoolB, Role::Bursar);
        $this->post(route('fees.categories.store'), ['name' => 'Tuition', 'code' => 'TUI'])
            ->assertRedirect(route('fees.categories.index'));

        $this->assertSame(2, FeeCategory::withoutGlobalScopes()->where('name', 'Tuition')->count());
    }

    public function test_teacher_staff_parent_and_student_cannot_manage_categories(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.categories.index'))->assertForbidden();
            $this->post(route('fees.categories.store'), ['name' => 'x'])->assertForbidden();
        }
    }

    public function test_principal_can_view_but_not_manage_categories(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Principal);

        $this->get(route('fees.categories.index'))->assertForbidden();
        $this->post(route('fees.categories.store'), ['name' => 'x'])->assertForbidden();
    }

    public function test_another_schools_category_404s_on_update(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolB);
        $category = FeeCategory::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->patch(route('fees.categories.update', $category), ['name' => 'Hacked'])->assertNotFound();
    }

    public function test_module_off_404s_category_routes(): void
    {
        $school = $this->newSchool();
        $this->disableFees($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('fees.categories.index'))->assertNotFound();
    }
}
