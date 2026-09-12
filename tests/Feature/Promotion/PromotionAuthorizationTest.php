<?php

namespace Tests\Feature\Promotion;

use App\Enums\Role;
use App\Models\PromotionBatch;

class PromotionAuthorizationTest extends PromotionTestCase
{
    private function batchFor($school, array $overrides = []): PromotionBatch
    {
        $this->enterSchool($school);
        $batch = PromotionBatch::factory()->create($overrides);
        $this->app->forgetScopedInstances();

        return $batch;
    }

    public function test_module_off_returns_404_for_every_promotion_route(): void
    {
        $school = $this->newSchool();
        $this->disablePromotion($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/promotion')->assertNotFound();
        $this->get('/promotion/create')->assertNotFound();
        $this->get('/promotion/graduation')->assertNotFound();
        $this->get('/promotion/graduation/create')->assertNotFound();
    }

    public function test_school_admin_and_principal_have_full_promotion_access(): void
    {
        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $school = $this->newSchool();
            $this->actingAsRole($school, $role);

            $this->get('/promotion')->assertOk();
            $this->get('/promotion/create')->assertOk();
        }
    }

    public function test_teacher_and_staff_can_view_but_not_manage_promotions(): void
    {
        foreach ([Role::Teacher, Role::Staff] as $role) {
            $school = $this->newSchool();
            $this->actingAsRole($school, $role);

            $this->get('/promotion')->assertOk();
            $this->get('/promotion/create')->assertForbidden();
            $this->post('/promotion', [])->assertForbidden();
        }
    }

    public function test_roles_with_no_promotion_access_are_forbidden(): void
    {
        foreach ([Role::Bursar, Role::Parent, Role::Student] as $role) {
            $school = $this->newSchool();
            $this->actingAsRole($school, $role);

            $this->get('/promotion')->assertForbidden();
            $this->get('/promotion/create')->assertForbidden();
        }
    }

    public function test_roleless_member_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, null);

        $this->get('/promotion')->assertForbidden();
    }

    public function test_a_school_cannot_view_another_schools_promotion_batch(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $batchB = $this->batchFor($schoolB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/promotion/{$batchB->id}")->assertNotFound();
    }

    public function test_a_school_cannot_use_another_schools_academic_ids_in_the_roster_request(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextB = $this->classContext($schoolB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get('/promotion/roster?'.http_build_query([
            'source_session' => $contextB['session']->id,
            'source_level' => $contextB['level']->id,
            'source_arm' => $contextB['arm']->id,
        ]))->assertNotFound();
    }

    public function test_a_school_cannot_promote_using_another_schools_academic_ids(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $contextB = $this->classContext($schoolB);
        $studentA = $this->enrolledStudent($schoolA, $contextA);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/promotion', [
            'source_academic_session_id' => $contextA['session']->id,
            'source_academic_level_id' => $contextA['level']->id,
            'source_level_arm_id' => $contextA['arm']->id,
            // Target ids belong to School B — must be rejected, never trusted.
            'target_academic_session_id' => $contextB['session']->id,
            'target_academic_level_id' => $contextB['level']->id,
            'target_level_arm_id' => $contextB['arm']->id,
            'student_ids' => [$studentA->id],
        ]);

        $response->assertSessionHasErrors(['target_academic_session_id', 'target_academic_level_id', 'target_level_arm_id']);
    }

    public function test_a_school_cannot_promote_another_schools_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $studentB = $this->enrolledStudent($schoolB, $this->classContext($schoolB));

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/promotion', [
            'source_academic_session_id' => $contextA['session']->id,
            'source_academic_level_id' => $contextA['level']->id,
            'source_level_arm_id' => $contextA['arm']->id,
            'target_academic_session_id' => $contextA['session']->id,
            'target_academic_level_id' => $contextA['level']->id,
            'target_level_arm_id' => $contextA['arm']->id,
            'student_ids' => [$studentB->id],
        ]);

        $response->assertSessionHasErrors('student_ids.0');
    }

    public function test_school_id_from_the_browser_is_ignored_when_promoting(): void
    {
        $schoolA = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $target = $this->classContext($schoolA);
        $studentA = $this->enrolledStudent($schoolA, $contextA);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->post('/promotion', [
            'school_id' => 999999,
            'source_academic_session_id' => $contextA['session']->id,
            'source_academic_level_id' => $contextA['level']->id,
            'source_level_arm_id' => $contextA['arm']->id,
            'target_academic_session_id' => $target['session']->id,
            'target_academic_level_id' => $target['level']->id,
            'target_level_arm_id' => $target['arm']->id,
            'student_ids' => [$studentA->id],
        ]);

        $studentA->refresh();
        $this->assertSame($schoolA->id, $studentA->school_id);
        $this->assertSame($schoolA->id, $studentA->currentEnrollment->school_id);
    }

    public function test_a_successful_promotion_redirects_to_the_batch_summary(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/promotion', [
            'source_academic_session_id' => $source['session']->id,
            'source_academic_level_id' => $source['level']->id,
            'source_level_arm_id' => $source['arm']->id,
            'target_academic_session_id' => $target['session']->id,
            'target_academic_level_id' => $target['level']->id,
            'target_level_arm_id' => $target['arm']->id,
            'student_ids' => [$student->id],
        ]);

        $response->assertRedirect();
        $batch = PromotionBatch::query()->latest('id')->first();
        $response->assertRedirectToRoute('promotion.show', $batch->id);

        $this->get($response->headers->get('Location'))->assertOk()->assertSee($student->displayName());
    }
}
