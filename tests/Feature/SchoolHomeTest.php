<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * M29.5 — a school's own public entry page (`/schools/{school}`, bound by
 * slug) and its public logo route. No login required; never establishes a
 * tenant context. See `SchoolHomeController`, `docs/school-settings.md`.
 */
class SchoolHomeTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_page_shows_the_schools_own_identity(): void
    {
        $school = $this->newSchool(['name' => 'Greenfield Academy']);
        $this->enterSchool($school);
        $school->settings()->create(['motto' => 'Knowledge and character']);
        $this->app->forgetScopedInstances();

        $this->get('/schools/'.$school->slug)
            ->assertOk()
            ->assertSee('Greenfield Academy')
            ->assertSee('Knowledge and character')
            ->assertSee('Powered by Eden Education Suite');
    }

    public function test_the_page_never_shows_another_schools_branding(): void
    {
        $a = $this->newSchool(['name' => 'Alpha Academy']);
        $b = $this->newSchool(['name' => 'Beta College']);

        $this->get('/schools/'.$a->slug)
            ->assertOk()
            ->assertSee('Alpha Academy')
            ->assertDontSee('Beta College');
    }

    public function test_a_suspended_schools_page_is_not_found(): void
    {
        $school = School::factory()->suspended()->create();

        $this->get('/schools/'.$school->slug)->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get('/schools/does-not-exist-1234')->assertNotFound();
    }

    public function test_login_and_register_links_carry_the_school_hint(): void
    {
        $school = $this->newSchool();

        $response = $this->get('/schools/'.$school->slug)->assertOk();
        $response->assertSee('href="'.route('login', ['school' => $school->slug]).'"', false);
        $response->assertSee('href="'.route('register', ['school' => $school->slug]).'"', false);
    }

    public function test_the_logo_is_served_publicly_and_is_school_scoped(): void
    {
        Storage::fake('local');

        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)]);
        $this->flushSession();
        $this->app->forgetScopedInstances();

        // Unauthenticated request, no tenant context — still works.
        $this->get('/schools/'.$school->slug.'/logo')->assertOk();

        // A different school with no logo of its own: 404, never falls
        // through to any other school's file.
        $other = $this->newSchool();
        $this->get('/schools/'.$other->slug.'/logo')->assertNotFound();
    }

    public function test_a_suspended_schools_logo_is_not_served(): void
    {
        Storage::fake('local');

        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)]);
        $this->flushSession();
        $this->app->forgetScopedInstances();

        $school->status = SchoolStatus::Suspended;
        $school->saveQuietly();

        $this->get('/schools/'.$school->slug.'/logo')->assertNotFound();
    }
}
