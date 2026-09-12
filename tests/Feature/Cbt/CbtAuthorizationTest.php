<?php

namespace Tests\Feature\Cbt;

use App\Enums\ExaminationStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\SchoolModule;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Cbt\ExaminationQuestionService;

class CbtAuthorizationTest extends CbtTestCase
{
    public function test_school_admin_and_principal_have_full_access(): void
    {
        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $school = $this->newSchool();
            $this->enableCbt($school);
            $this->actingAsRole($school, $role);

            $this->get('/cbt/examinations')->assertOk();
            $this->get('/cbt/examinations/create')->assertOk();
            $this->get('/cbt/questions')->assertOk();
            $this->get('/cbt/questions/create')->assertOk();
        }
    }

    public function test_staff_is_view_only(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->actingAsRole($school, Role::Staff);

        $this->get('/cbt/examinations')->assertOk();
        $this->get('/cbt/examinations/create')->assertForbidden();
        $this->get('/cbt/questions')->assertOk();
        $this->get('/cbt/questions/create')->assertForbidden();
    }

    public function test_bursar_and_parent_have_no_staff_access(): void
    {
        foreach ([Role::Bursar, Role::Parent] as $role) {
            $school = $this->newSchool();
            $this->enableCbt($school);
            $this->actingAsRole($school, $role);

            $this->get('/cbt/examinations')->assertForbidden();
            $this->get('/cbt/questions')->assertForbidden();
        }
    }

    public function test_student_role_cannot_reach_staff_routes(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->actingAsRole($school, Role::Student);

        $this->get('/cbt/examinations')->assertForbidden();
    }

    public function test_roleless_member_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->actingAsRole($school, null);

        $this->get('/cbt/examinations')->assertForbidden();
    }

    public function test_module_off_returns_404_for_every_staff_route(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/cbt/examinations')->assertNotFound();
        $this->get('/cbt/questions')->assertNotFound();
        $this->post('/cbt/examinations', [])->assertNotFound();
    }

    public function test_module_off_never_affects_assessments_or_results(): void
    {
        // A regression guard: toggling the CBT module off must never
        // touch the unrelated M14/M15 modules.
        $school = $this->newSchool();
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'assessments', 'enabled' => true]);
        SchoolModule::query()->create(['module' => 'results', 'enabled' => true]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/cbt/examinations')->assertNotFound();
        $this->get('/assessments')->assertOk();
        $this->get('/results/runs')->assertOk();
    }

    public function test_a_school_cannot_view_another_schools_attempts_list(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $examB = $this->examinationIn($schoolB, $contextB);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$examB->id}/attempts")->assertNotFound();
    }

    public function test_teacher_cannot_schedule_or_close_an_exam_they_do_not_author(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $otherContext = $this->classContext($school);
        $exam = $this->examinationIn($school, $otherContext);
        $question = $this->questionIn($school, $otherContext['subject']);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $this->app->forgetScopedInstances();

        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->post("/cbt/examinations/{$exam->id}/schedule")->assertForbidden();
    }

    public function test_teacher_with_active_assignment_can_manage_their_own_exam(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $this->app->forgetScopedInstances();

        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->post("/cbt/examinations/{$exam->id}/schedule")->assertRedirect(route('cbt.examinations.show', $exam->id));
        $this->assertSame(ExaminationStatus::Scheduled, $exam->fresh()->status);
    }

    public function test_teacher_assignment_ended_no_longer_grants_access(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);

        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Teacher);
        $teacher = Teacher::factory()->create();
        $teacher->user_id = $user->id;
        $teacher->save();
        $teacher->assignments()->create([
            'academic_session_id' => $context['session']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
            'subject_id' => $context['subject']->id,
            'status' => 'ended',
            'started_on' => $context['session']->starts_on->toDateString(),
            'ended_on' => now()->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->id]);

        $this->get("/cbt/examinations/{$exam->id}/edit")->assertForbidden();
    }
}
