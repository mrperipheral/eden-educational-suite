<?php

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Http\Middleware\EnforceTenant;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolProvisioningTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // ---- authorization ------------------------------------------------------

    public function test_platform_admin_can_view_and_open_the_provisioning_flow(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get('/admin/schools')->assertOk();
        $this->actingAs($admin)->get('/admin/schools/create')->assertOk();
    }

    public function test_school_users_cannot_reach_school_provisioning(): void
    {
        $school = $this->newSchool();

        foreach ([Role::SchoolAdmin, Role::Principal, Role::Teacher, null] as $role) {
            $user = $this->memberOf($school, $role);

            $this->actingAs($user)->get('/admin/schools')->assertForbidden();
            $this->actingAs($user)->get('/admin/schools/create')->assertForbidden();
            $this->actingAs($user)->post('/admin/schools', ['name' => 'Hacked School'])->assertForbidden();
        }

        $this->assertDatabaseMissing('schools', ['name' => 'Hacked School']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/schools')->assertRedirect('/login');
        $this->post('/admin/schools', ['name' => 'X'])->assertRedirect('/login');
    }

    // ---- creation & validation -------------------------------------------

    public function test_platform_admin_can_create_a_school(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->post('/admin/schools', [
            'name' => 'Riverside Grammar',
            'slug' => 'riverside-grammar',
        ]);

        $school = School::query()->where('slug', 'riverside-grammar')->firstOrFail();
        $response->assertRedirect(route('admin.schools.show', $school));

        $this->assertSame('Riverside Grammar', $school->name);
        $this->assertSame(SchoolStatus::Active, $school->status, 'new schools start active');
    }

    public function test_slug_is_generated_from_the_name_when_omitted(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post('/admin/schools', ['name' => 'Bright Future Academy']);

        $this->assertDatabaseHas('schools', ['slug' => 'bright-future-academy']);
    }

    public function test_generated_slug_is_made_unique(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        School::factory()->create(['slug' => 'unity-school']);

        $this->actingAs($admin)->post('/admin/schools', ['name' => 'Unity School']);

        $this->assertDatabaseHas('schools', ['slug' => 'unity-school-2']);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        School::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($admin)->from('/admin/schools/create')->post('/admin/schools', [
            'name' => 'Another School',
            'slug' => 'taken-slug',
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('schools', ['name' => 'Another School']);
    }

    public function test_invalid_slug_format_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // (leading/trailing whitespace and casing are normalised, so those are
        //  accepted — these are genuinely malformed)
        foreach (['Has Spaces', 'trailing-', 'under_score', 'emoji😀', '--double'] as $slug) {
            $this->actingAs($admin)->from('/admin/schools/create')->post('/admin/schools', [
                'name' => 'Whatever',
                'slug' => $slug,
            ])->assertSessionHasErrors('slug');
        }
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->from('/admin/schools/create')->post('/admin/schools', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    // ---- initial school admin -------------------------------------------

    public function test_an_existing_active_user_can_be_seated_as_the_initial_school_admin(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $futureAdmin = User::factory()->create(['email' => 'head@school.example']);

        $this->actingAs($admin)->post('/admin/schools', [
            'name' => 'Green Valley',
            'initial_admin_email' => 'head@school.example',
        ]);

        $school = School::query()->where('slug', 'green-valley')->firstOrFail();

        $this->assertSame(Role::SchoolAdmin, $this->storedRole($futureAdmin, $school));
        $this->assertTrue($school->hasSchoolAdmin());
    }

    public function test_initial_admin_email_must_belong_to_an_existing_active_account(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $suspended = User::factory()->suspended()->create(['email' => 'suspended@x.example']);

        $this->actingAs($admin)->from('/admin/schools/create')->post('/admin/schools', [
            'name' => 'No Admin School',
            'initial_admin_email' => 'nobody@nowhere.example',
        ])->assertSessionHasErrors('initial_admin_email');

        $this->actingAs($admin)->from('/admin/schools/create')->post('/admin/schools', [
            'name' => 'No Admin School',
            'initial_admin_email' => 'suspended@x.example',
        ])->assertSessionHasErrors('initial_admin_email');

        $this->assertDatabaseMissing('schools', ['name' => 'No Admin School']);
    }

    // ---- context / isolation --------------------------------------------

    public function test_provisioning_does_not_set_the_platform_admins_tenant_context(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post('/admin/schools', ['name' => 'Contextless School']);

        $this->assertNull(session(EnforceTenant::SESSION_KEY));
    }

    public function test_show_page_renders_and_offers_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $school = $this->newSchool(['name' => 'Displayed School']);

        $this->actingAs($admin)->get(route('admin.schools.show', $school))
            ->assertOk()
            ->assertSee('Displayed School')
            ->assertSee(route('school-context.store'), false);
    }

    public function test_school_list_is_searchable_and_paginated(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        School::factory()->count(25)->create();
        $this->newSchool(['name' => 'Findable Montessori']);

        $this->actingAs($admin)->get('/admin/schools')->assertOk()->assertSee('page=2', false);
        $this->actingAs($admin)->get('/admin/schools?q=Findable')
            ->assertOk()
            ->assertSee('Findable Montessori');
    }
}
