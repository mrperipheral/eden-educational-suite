<?php

namespace Tests\Feature\LearningMaterials;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicSession;
use App\Models\LearningMaterial;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class StudentLearningMaterialTest extends LearningMaterialTestCase
{
    /** A student enrolled in the given class context, linked to a fresh Student-role user. */
    private function studentUser(School $school, array $ctx): User
    {
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $student->enrollments()->create([
            'academic_session_id' => $ctx['session']->id,
            'academic_level_id' => $ctx['level']->id,
            'level_arm_id' => $ctx['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $ctx['session']->starts_on->toDateString(),
        ]);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    private function materialIn(array $ctx, array $overrides = []): LearningMaterial
    {
        return LearningMaterial::factory()->create(array_merge([
            'academic_session_id' => $ctx['session']->id,
            'academic_level_id' => $ctx['level']->id,
            'level_arm_id' => $ctx['arm']->id ?? null,
            'subject_id' => $ctx['subject']->id,
        ], $overrides));
    }

    public function test_student_sees_materials_for_their_own_current_class(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $material = $this->materialIn($ctx, ['title' => 'Own class notes']);
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $response = $this->get('/student/learning-materials');

        $response->assertOk()->assertSee('Own class notes');
    }

    public function test_student_sees_whole_level_materials_regardless_of_their_own_arm(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $material = $this->materialIn($ctx, ['level_arm_id' => null, 'title' => 'Whole level note']);
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $this->get('/student/learning-materials')->assertOk()->assertSee('Whole level note');
    }

    public function test_student_does_not_see_a_different_arms_material(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $otherArm = $ctx['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $material = $this->materialIn($ctx, ['level_arm_id' => $otherArm->id, 'title' => 'Silver-only note']);
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $this->get('/student/learning-materials')->assertOk()->assertDontSee('Silver-only note');
    }

    public function test_student_does_not_see_a_different_sessions_material(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $material = $this->materialIn($ctx, ['academic_session_id' => $otherSession->id, 'title' => 'Old session note']);
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $this->get('/student/learning-materials')->assertOk()->assertDontSee('Old session note');
    }

    public function test_module_off_shows_an_empty_state_not_a_404(): void
    {
        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->studentUser($school, $ctx);

        $this->get('/student/learning-materials')->assertOk();
    }

    public function test_unlinked_student_account_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get('/student/learning-materials')->assertOk();
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $this->actingAsRole($school, Role::Teacher);

        $this->get('/student/learning-materials')->assertForbidden();
    }

    public function test_student_can_download_their_own_class_material(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $material = $this->materialIn($ctx);
        Storage::disk(LearningMaterial::FILE_DISK)->put($material->file_path, 'content');
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $this->get("/student/learning-materials/{$material->id}/download")->assertOk();
    }

    public function test_student_cannot_download_a_material_outside_their_current_class(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $otherCtx = $this->classContext($school);

        $this->enterSchool($school);
        $material = $this->materialIn($otherCtx);
        $this->app->forgetScopedInstances();

        $this->studentUser($school, $ctx);

        $this->get("/student/learning-materials/{$material->id}/download")->assertNotFound();
    }

    public function test_student_cannot_download_another_schools_material(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableLearningMaterials($schoolA);
        $this->enableLearningMaterials($schoolB);
        $ctxA = $this->classContext($schoolA);
        $ctxB = $this->classContext($schoolB);

        $this->enterSchool($schoolB);
        $materialB = $this->materialIn($ctxB);
        $this->app->forgetScopedInstances();

        $this->studentUser($schoolA, $ctxA);

        $this->get("/student/learning-materials/{$materialB->id}/download")->assertNotFound();
    }
}
