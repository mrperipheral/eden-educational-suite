<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnforceTenant;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolContextControllerTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_picker_requires_authentication(): void
    {
        $this->get('/school')->assertRedirect('/login');
    }

    public function test_member_sees_only_their_own_schools(): void
    {
        $mine = $this->newSchool(['name' => 'My School']);
        $notMine = $this->newSchool(['name' => 'Someone Elses School']);
        $user = $this->memberOf($mine);

        $this->actingAs($user)->get('/school')
            ->assertOk()
            ->assertSee('My School')
            ->assertDontSee('Someone Elses School');
    }

    public function test_platform_admin_sees_all_schools(): void
    {
        $this->newSchool(['name' => 'School One']);
        $this->newSchool(['name' => 'School Two']);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get('/school')
            ->assertOk()
            ->assertSee('School One')
            ->assertSee('School Two');
    }

    public function test_school_list_is_paginated(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        School::factory()->count(20)->create();

        $response = $this->actingAs($admin)->get('/school');

        $response->assertOk();
        // 15 per page → a second page must exist
        $response->assertSee('page=2', false);
    }

    public function test_search_filters_the_list(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->newSchool(['name' => 'Greenfield Academy']);
        $this->newSchool(['name' => 'Riverside College']);

        $this->actingAs($admin)->get('/school?q=Green')
            ->assertOk()
            ->assertSee('Greenfield Academy')
            ->assertDontSee('Riverside College');
    }

    public function test_member_can_select_a_school_they_belong_to(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school);

        $this->actingAs($user)->post('/school', ['school' => $school->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($school->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_member_cannot_select_a_school_they_do_not_belong_to(): void
    {
        $school = $this->newSchool();
        $user = User::factory()->create(); // not a member of anything

        $this->actingAs($user)->post('/school', ['school' => $school->id])
            ->assertForbidden();

        $this->assertNull(session(EnforceTenant::SESSION_KEY));
    }

    public function test_cannot_select_a_suspended_school(): void
    {
        $school = School::factory()->suspended()->create();
        $user = $this->memberOf($school);

        $this->actingAs($user)->post('/school', ['school' => $school->id])
            ->assertForbidden();
    }

    public function test_selecting_a_nonexistent_school_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/school')->post('/school', ['school' => 999999])
            ->assertSessionHasErrors('school');
    }

    public function test_platform_admin_can_select_any_active_school(): void
    {
        $school = $this->newSchool();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post('/school', ['school' => $school->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($school->id, session(EnforceTenant::SESSION_KEY));
    }

    // -- M29.5: only a Platform Admin may switch ---------------------------

    public function test_a_non_admin_already_working_in_a_school_cannot_switch_to_a_different_one(): void
    {
        $current = $this->newSchool();
        $other = $this->newSchool();
        $user = $this->memberOf($current);
        $user->joinSchool($other);

        $this->actingAs($user)->withSession([EnforceTenant::SESSION_KEY => $current->id])
            ->post('/school', ['school' => $other->id])
            ->assertForbidden();

        // Session is untouched — still in the original school.
        $this->assertSame($current->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_a_non_admin_already_working_in_a_school_is_sent_to_the_dashboard_not_the_picker(): void
    {
        $current = $this->newSchool();
        $other = $this->newSchool();
        $user = $this->memberOf($current);
        $user->joinSchool($other);

        $this->actingAs($user)->withSession([EnforceTenant::SESSION_KEY => $current->id])
            ->get('/school')
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_non_admin_member_of_two_schools_can_still_pick_one_on_first_entry(): void
    {
        // No active session school yet (e.g. fresh sign-in with no stored
        // choice) — this is a legitimate first entry, not a "switch", and
        // must still work even though the member belongs to more than one
        // school.
        $a = $this->newSchool();
        $b = $this->newSchool();
        $user = $this->memberOf($a);
        $user->joinSchool($b);

        $this->actingAs($user)->post('/school', ['school' => $a->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($a->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_a_platform_admin_can_switch_from_an_already_active_school_to_a_different_one(): void
    {
        $current = $this->newSchool();
        $other = $this->newSchool();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->withSession([EnforceTenant::SESSION_KEY => $current->id])
            ->post('/school', ['school' => $other->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($other->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_re_selecting_the_same_school_is_not_treated_as_a_switch(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school);

        $this->actingAs($user)->withSession([EnforceTenant::SESSION_KEY => $school->id])
            ->post('/school', ['school' => $school->id])
            ->assertRedirect(route('dashboard'));
    }
}
