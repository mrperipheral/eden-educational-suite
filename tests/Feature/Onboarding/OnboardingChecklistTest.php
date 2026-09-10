<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_fresh_school_shows_outstanding_steps_to_its_admin(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Finish setting up')
            ->assertSee('Create the first academic session')
            ->assertSee('Review school settings');
    }

    public function test_steps_tick_off_as_they_are_completed(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // Admin step is already done (this user is a School Admin).
        $this->get('/dashboard')->assertOk()->assertDontSee('School onboarding is complete');

        // Complete settings + a session.
        $this->patch('/settings/school', ['timezone' => 'Africa/Lagos', 'locale' => 'en']);
        $this->post('/settings/academic-sessions', [
            'name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31',
        ]);

        $this->get('/dashboard')->assertOk()->assertSee('School onboarding is complete');
    }

    public function test_checklist_is_hidden_from_users_who_cannot_act_on_it(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Teacher, Role::Bursar, Role::Parent, null] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/dashboard')->assertOk()->assertDontSee('Finish setting up');
        }
    }

    public function test_admin_step_reflects_membership_not_the_viewer(): void
    {
        $school = $this->newSchool();
        // A platform admin viewing a school that has a real School Admin member.
        $this->memberOf($school, Role::SchoolAdmin);
        $this->actingAsPlatformAdmin($school);

        $response = $this->get('/dashboard')->assertOk();
        $response->assertSee('Assign a School Admin');
        // that step is ticked (line-through) because the school has an admin
        $response->assertSee('line-through', false);
    }
}
