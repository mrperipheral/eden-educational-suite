<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnforceTenant;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EnforceTenantTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_single_school_member_is_auto_resolved(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee($school->name);

        $this->assertSame($school->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_multi_school_member_without_a_selection_is_sent_to_the_picker(): void
    {
        $user = User::factory()->create();
        $user->schools()->attach([$this->newSchool()->id, $this->newSchool()->id]);

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('school-context.create'));
    }

    public function test_a_valid_session_selection_is_honoured(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $user = User::factory()->create();
        $user->schools()->attach([$a->id, $b->id]);

        $this->actingAs($user)
            ->withSession([EnforceTenant::SESSION_KEY => $b->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($b->name);
    }

    public function test_a_session_selection_the_user_cannot_access_is_discarded(): void
    {
        $mine = $this->newSchool();
        $notMine = $this->newSchool();
        $user = $this->memberOf($mine);

        // Tamper: point the session at a school the user does not belong to.
        $this->actingAs($user)
            ->withSession([EnforceTenant::SESSION_KEY => $notMine->id])
            ->get('/dashboard')
            // falls back to the single school they *do* belong to
            ->assertOk()
            ->assertSee($mine->name);

        $this->assertSame($mine->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_a_suspended_school_selection_is_not_honoured(): void
    {
        $suspended = School::factory()->suspended()->create();
        $user = $this->memberOf($suspended);

        $this->actingAs($user)
            ->withSession([EnforceTenant::SESSION_KEY => $suspended->id])
            ->get('/dashboard')
            ->assertRedirect(route('school-context.create'));
    }

    public function test_member_of_no_school_is_sent_to_the_picker(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('school-context.create'));
    }

    public function test_platform_admin_is_never_auto_resolved_even_with_one_membership(): void
    {
        $school = $this->newSchool();
        $admin = User::factory()->platformAdmin()->create();
        $admin->schools()->attach($school);

        $this->actingAs($admin)->get('/dashboard')
            ->assertRedirect(route('school-context.create'));
    }

    public function test_platform_admin_with_a_selection_gets_that_context(): void
    {
        $school = $this->newSchool();
        $admin = User::factory()->platformAdmin()->create(); // not a member

        $this->actingAs($admin)
            ->withSession([EnforceTenant::SESSION_KEY => $school->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($school->name);
    }

    public function test_guest_hitting_a_tenant_route_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_is_stopped_before_tenant_resolution(): void
    {
        $user = User::factory()->unverified()->create();
        $user->schools()->attach($this->newSchool());

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }
}
