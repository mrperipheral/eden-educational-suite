<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\Subject;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class SubjectTest extends AcademicTestCase
{
    private function subjects(int $schoolId)
    {
        return Subject::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_create_edit_and_deactivate_a_subject(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/subjects', ['name' => 'Mathematics', 'code' => 'mth', 'description' => 'Core', 'position' => 2])
            ->assertRedirect(route('academic.subjects.index'));

        $subject = $this->subjects($school->id)->firstOrFail();
        $this->assertSame('MTH', $subject->code);
        $this->assertSame('Core', $subject->description);

        $this->patch("/academic/subjects/{$subject->id}", ['name' => 'Maths', 'code' => 'MTH', 'is_active' => '0'])
            ->assertRedirect(route('academic.subjects.index'));
        $this->assertFalse((bool) $subject->fresh()->is_active);
    }

    public function test_name_and_code_are_unique_per_school(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/subjects', ['name' => 'English', 'code' => 'ENG']);
        $this->from('/academic/subjects')->post('/academic/subjects', ['name' => 'English', 'code' => 'X'])->assertSessionHasErrors('name');
        $this->from('/academic/subjects')->post('/academic/subjects', ['name' => 'Y', 'code' => 'ENG'])->assertSessionHasErrors('code');

        $this->assertSame(1, $this->subjects($school->id)->count());
    }

    public function test_search_filters_the_list(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Subject::factory()->create(['name' => 'Geography', 'code' => 'GEO']);
        Subject::factory()->create(['name' => 'Chemistry', 'code' => 'CHM']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/academic/subjects?q=geog')->assertOk()->assertSee('Geography')->assertDontSee('Chemistry');
        $this->get('/academic/subjects?q=CHM')->assertOk()->assertSee('Chemistry')->assertDontSee('Geography');
    }

    public function test_authorization_and_module_gate(): void
    {
        $school = $this->newSchool();

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/subjects')->assertOk();
            $this->from('/academic/subjects')->post('/academic/subjects', ['name' => "S-{$role->value}", 'code' => 'S'])->assertForbidden();
            $this->flushSession();
        }

        foreach ($this->academicRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/subjects')->assertForbidden();
            $this->flushSession();
        }

        $this->disableAcademics($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/academic/subjects')->assertNotFound();
    }

    public function test_subjects_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $subjectA = Subject::factory()->create(['name' => 'A-Subject', 'code' => 'ASB']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/academic/subjects')->assertOk()->assertDontSee('A-Subject');
        $this->get("/academic/subjects/{$subjectA->id}/edit")->assertNotFound();
        $this->patch("/academic/subjects/{$subjectA->id}", ['name' => 'x', 'code' => 'x'])->assertNotFound();

        $this->assertSame('A-Subject', $subjectA->fresh()->name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $subject = Subject::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $subject->school_id = $b->id;
        $subject->save();
    }
}
