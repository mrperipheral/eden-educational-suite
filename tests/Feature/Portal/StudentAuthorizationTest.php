<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;

/**
 * Module gating, permission gating, safe empty states, and role boundaries
 * for the Student Portal (see `docs/student-portal.md`).
 */
class StudentAuthorizationTest extends StudentPortalTestCase
{
    public function test_the_portal_is_unavailable_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->disableStudentPortal($school);
        $this->actingAsStudentUser($school, $user);

        $this->get('/student')->assertNotFound();
    }

    public function test_roles_without_the_student_permission_are_forbidden(): void
    {
        $school = $this->newSchool();

        foreach ($this->studentPortalRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/student')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_unauthenticated_visitors_are_redirected_to_login(): void
    {
        $this->get('/student')->assertRedirect(route('login'));
    }

    public function test_a_student_role_account_with_no_linked_student_record_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school, Role::Student);
        $this->actingAsStudentUser($school, $user);

        $this->get('/student')
            ->assertOk()
            ->assertSee(__('Your account is not linked to a student record yet'));
    }

    public function test_a_linked_student_sees_their_own_dashboard(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $this->actingAsStudentUser($school, $user);

        $this->get('/student')->assertOk()->assertSee($student->fullName());
    }

    public function test_disabled_account_cannot_authenticate_into_the_portal(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $user = User::factory()->create(['status' => UserStatus::Suspended]);
        $user->joinSchool($school, Role::Student);
        $student = Student::factory()->create();
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student')->assertRedirect(route('login'));
    }

    /**
     * A Student-role account must not reach any management area, even when
     * the module happens to be on.
     */
    public function test_a_student_cannot_reach_management_areas(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->actingAsStudentUser($school, $user);

        foreach ([
            '/members', '/academic/sessions', '/students', '/guardians', '/teachers',
            '/attendance', '/assessments', '/results/runs', '/settings/school',
        ] as $path) {
            $this->get($path)->assertForbidden();
        }

        $this->get('/admin/schools')->assertForbidden();
    }
}
