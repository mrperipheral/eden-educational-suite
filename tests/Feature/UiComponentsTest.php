<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Smoke tests for the shared Blade UI foundation. These guard against the
 * components failing to compile as they evolve; they are not visual tests.
 */
class UiComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Render layouts without needing a built Vite manifest in the test run.
        $this->withoutVite();

        // The web middleware group shares $errors on every real request; make
        // it available here so form components can be rendered in isolation.
        View::share('errors', new ViewErrorBag);
    }

    public function test_core_components_compile_and_render(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-button variant="primary">Save</x-button>
            <x-button href="/somewhere" variant="secondary">Link</x-button>
            <x-badge variant="success">Active</x-badge>
            <x-alert variant="danger" title="Oops">Something failed</x-alert>
            <x-card title="Panel">Body</x-card>
            <x-page-header title="People" description="All users" />
            <x-empty-state title="No records" description="Add one to begin" />
            <x-spinner size="sm" />
            <x-input name="email" label="Email" />
        BLADE);

        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('href="/somewhere"', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Something failed', $html);
        $this->assertStringContainsString('Panel', $html);
        $this->assertStringContainsString('People', $html);
        $this->assertStringContainsString('No records', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_app_layout_renders_shell(): void
    {
        $html = Blade::render('<x-layouts.app>Dashboard content</x-layouts.app>');

        $this->assertStringContainsString('Dashboard content', $html);
        $this->assertStringContainsString('sidebarOpen', $html);
    }

    public function test_guest_layout_renders(): void
    {
        $html = Blade::render('<x-layouts.guest>Sign in form</x-layouts.guest>');

        $this->assertStringContainsString('Sign in form', $html);
    }

    // -- M29.5: personalized greeting ------------------------------------

    public function test_greeting_shows_the_right_message_for_the_time_of_day(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ada Lovelace']));

        $cases = [
            '05:00' => 'Good morning, Ada',
            '11:59' => 'Good morning, Ada',
            '12:00' => 'Good afternoon, Ada',
            '16:59' => 'Good afternoon, Ada',
            '17:00' => 'Good evening, Ada',
            '02:00' => 'Good evening, Ada',
            '04:59' => 'Good evening, Ada',
        ];

        foreach ($cases as $time => $expected) {
            Carbon::setTestNow(Carbon::parse($time));
            $this->assertStringContainsString($expected, Blade::render('<x-greeting />'), "at {$time}");
        }

        Carbon::setTestNow();
    }

    public function test_greeting_shows_an_optional_context_line(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ada Lovelace']));

        $html = Blade::render('<x-greeting :context="$context" />', ['context' => 'Here is your day.']);

        $this->assertStringContainsString('Here is your day.', $html);
    }
}
