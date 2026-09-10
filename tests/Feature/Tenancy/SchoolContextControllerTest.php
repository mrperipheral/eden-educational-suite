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
}
