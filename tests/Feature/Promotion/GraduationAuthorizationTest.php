<?php

namespace Tests\Feature\Promotion;

use App\Enums\Role;
use App\Enums\StudentStatus;

class GraduationAuthorizationTest extends PromotionTestCase
{
    public function test_module_off_returns_404_for_graduation_routes(): void
    {
        $school = $this->newSchool();
        $this->disablePromotion($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/promotion/graduation')->assertNotFound();
        $this->get('/promotion/graduation/create')->assertNotFound();
        $this->post('/promotion/graduation', [])->assertNotFound();
    }

    public function test_school_admin_and_principal_can_manage_graduation(): void
    {
        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $school = $this->newSchool();
            $this->actingAsRole($school, $role);

            $this->get('/promotion/graduation')->assertOk();
            $this->get('/promotion/graduation/create')->assertOk();
        }
    }

    public function test_view_only_and_unrelated_roles_cannot_manage_graduation(): void
    {
        foreach ([Role::Teacher, Role::Staff, Role::Bursar, Role::Parent, Role::Student] as $role) {
            $school = $this->newSchool();
            $this->actingAsRole($school, $role);

            $this->get('/promotion/graduation/create')->assertForbidden();
            $this->post('/promotion/graduation', [])->assertForbidden();
        }
    }

    public function test_teacher_and_staff_can_still_view_the_graduation_history(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Teacher);

        $this->get('/promotion/graduation')->assertOk();
    }

    public function test_bursar_cannot_view_the_graduation_history(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Bursar);

        $this->get('/promotion/graduation')->assertForbidden();
    }

    public function test_a_school_cannot_graduate_another_schools_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $studentB = $this->enrolledStudent($schoolB, $this->classContext($schoolB));

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/promotion/graduation', [
            'academic_session_id' => $contextA['session']->id,
            'student_ids' => [$studentB->id],
        ]);

        $response->assertSessionHasErrors('student_ids.0');
    }

    public function test_a_school_cannot_use_another_schools_session_to_graduate(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $sessionB = $this->classContext($schoolB)['session'];
        $studentA = $this->enrolledStudent($schoolA, $contextA);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/promotion/graduation', [
            'academic_session_id' => $sessionB->id,
            'student_ids' => [$studentA->id],
        ]);

        $response->assertSessionHasErrors('academic_session_id');
    }

    public function test_a_school_cannot_reactivate_another_schools_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextB = $this->classContext($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $contextB, ['status' => StudentStatus::Graduated->value]);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->post("/promotion/graduation/{$studentB->id}/reactivate")->assertNotFound();
    }

    public function test_a_successful_graduation_redirects_to_the_graduation_history(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/promotion/graduation', [
            'academic_session_id' => $context['session']->id,
            'student_ids' => [$student->id],
            'notes' => 'Graduated at the top of the class.',
        ]);

        $response->assertRedirect(route('promotion.graduation.index'));
        $this->assertSame(StudentStatus::Graduated, $student->fresh()->status);
    }

    public function test_reactivate_returns_the_student_to_active_status(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/promotion/graduation/{$student->id}/reactivate")
            ->assertRedirect(route('students.show', $student->id));

        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
    }

    public function test_view_only_role_cannot_reactivate(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->actingAsRole($school, Role::Teacher);

        $this->post("/promotion/graduation/{$student->id}/reactivate")->assertForbidden();
        $this->assertSame(StudentStatus::Graduated, $student->fresh()->status);
    }

    public function test_school_id_from_the_browser_is_ignored_when_graduating(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/promotion/graduation', [
            'school_id' => 999999,
            'academic_session_id' => $context['session']->id,
            'student_ids' => [$student->id],
        ]);

        $student->refresh();
        $this->assertSame($school->id, $student->school_id);
    }
}
