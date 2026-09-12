<?php

namespace Tests\Feature\LearningMaterials;

use App\Enums\Role;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LearningMaterialAuthorizationTest extends LearningMaterialTestCase
{
    private function payload(array $ctx, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $ctx['session']->id,
            'academic_level_id' => $ctx['level']->id,
            'level_arm_id' => $ctx['arm']->id,
            'subject_id' => $ctx['subject']->id,
            'title' => 'Some notes',
        ], $overrides);
    }

    public function test_module_off_returns_404_for_every_route(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/learning-materials')->assertNotFound();
        $this->get('/learning-materials/create')->assertNotFound();
        $this->post('/learning-materials', [])->assertNotFound();
    }

    public function test_school_admin_and_principal_have_full_access(): void
    {
        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $school = $this->newSchool();
            $this->enableLearningMaterials($school);
            $this->actingAsRole($school, $role);

            $this->get('/learning-materials')->assertOk();
            $this->get('/learning-materials/create')->assertOk();
        }
    }

    public function test_staff_can_view_but_not_upload(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $this->actingAsRole($school, Role::Staff);

        $this->get('/learning-materials')->assertOk();
        $this->get('/learning-materials/create')->assertForbidden();
        $this->post('/learning-materials', [])->assertForbidden();
    }

    public function test_roles_with_no_access_are_forbidden(): void
    {
        foreach ([Role::Bursar, Role::Parent, Role::Student] as $role) {
            $school = $this->newSchool();
            $this->enableLearningMaterials($school);
            $this->actingAsRole($school, $role);

            $this->get('/learning-materials')->assertForbidden();
            $this->get('/learning-materials/create')->assertForbidden();
        }
    }

    public function test_roleless_member_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $this->actingAsRole($school, null);

        $this->get('/learning-materials')->assertForbidden();
    }

    public function test_teacher_can_upload_for_their_own_assigned_class(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $this->teacherAssignedTo($school, $ctx, $ctx['arm']);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertRedirect(route('learning-materials.index'));
        $this->assertSame(1, LearningMaterial::query()->count());
    }

    public function test_teacher_cannot_upload_for_a_class_they_do_not_teach(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $otherCtx = $this->classContext($school);
        $this->teacherAssignedTo($school, $ctx, $ctx['arm']);

        $response = $this->post('/learning-materials', array_merge($this->payload($otherCtx), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertForbidden();
        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_teacher_assigned_arm_agnostically_can_upload_for_any_arm_of_that_level(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        // Arm-agnostic assignment — pass null for the assignment's own arm.
        $this->teacherAssignedTo($school, $ctx, null);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertRedirect(route('learning-materials.index'));
    }

    public function test_a_tampered_video_upload_is_rejected_server_side_even_with_a_disguised_extension(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'file' => UploadedFile::fake()->create('sneaky.pdf', 500, 'video/mp4'),
        ]));

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_an_honest_video_upload_is_rejected(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'file' => UploadedFile::fake()->create('lecture.mp4', 500, 'video/mp4'),
        ]));

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_a_school_cannot_view_another_schools_material(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableLearningMaterials($schoolA);
        $this->enableLearningMaterials($schoolB);
        $ctxB = $this->classContext($schoolB);

        $this->enterSchool($schoolB);
        $materialB = LearningMaterial::factory()->create([
            'academic_session_id' => $ctxB['session']->id,
            'academic_level_id' => $ctxB['level']->id,
            'subject_id' => $ctxB['subject']->id,
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/learning-materials/{$materialB->id}/download")->assertNotFound();
        $this->delete("/learning-materials/{$materialB->id}")->assertNotFound();
    }

    public function test_a_school_cannot_upload_using_another_schools_academic_ids(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableLearningMaterials($schoolA);
        $ctxB = $this->classContext($schoolB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctxB), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors(['academic_session_id', 'academic_level_id', 'level_arm_id', 'subject_id']);
    }

    public function test_subject_not_offered_at_the_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/learning-materials', array_merge($this->payload($ctx, ['subject_id' => $otherSubject->id]), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_teacher_can_delete_their_own_upload_but_not_anothers(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $teacher = $this->teacherAssignedTo($school, $ctx, $ctx['arm']);

        $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));
        $own = LearningMaterial::query()->firstOrFail();

        $this->enterSchool($school);
        $admin = User::factory()->create();
        $others = LearningMaterial::factory()->create([
            'academic_session_id' => $ctx['session']->id,
            'academic_level_id' => $ctx['level']->id,
            'level_arm_id' => $ctx['arm']->id,
            'subject_id' => $ctx['subject']->id,
            'uploaded_by' => $admin->id,
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAs($teacher);

        $this->delete("/learning-materials/{$others->id}")->assertForbidden();
        $this->delete("/learning-materials/{$own->id}")->assertRedirect(route('learning-materials.index'));
        $this->assertSame(1, LearningMaterial::query()->count());
    }

    public function test_manage_holder_can_delete_any_material(): void
    {
        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);

        $this->enterSchool($school);
        $material = LearningMaterial::factory()->create([
            'academic_session_id' => $ctx['session']->id,
            'academic_level_id' => $ctx['level']->id,
            'subject_id' => $ctx['subject']->id,
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Principal);

        $this->delete("/learning-materials/{$material->id}")->assertRedirect(route('learning-materials.index'));
        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_school_id_from_the_browser_is_ignored(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $this->enableLearningMaterials($school);
        $ctx = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/learning-materials', array_merge($this->payload($ctx), [
            'school_id' => 999999,
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]));

        $material = LearningMaterial::query()->firstOrFail();
        $this->assertSame($school->id, $material->school_id);
    }
}
