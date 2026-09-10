<?php

namespace Tests\Feature;

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
}
