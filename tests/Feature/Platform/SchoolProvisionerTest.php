<?php

namespace Tests\Feature\Platform;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Services\SchoolProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisioner = new SchoolProvisioner;
    }

    public function test_it_creates_an_active_school_with_a_slug_from_the_name(): void
    {
        $school = $this->provisioner->provision('Harmony High');

        $this->assertSame('Harmony High', $school->name);
        $this->assertSame('harmony-high', $school->slug);
        $this->assertSame(SchoolStatus::Active, $school->status);
    }

    public function test_it_honours_an_explicit_slug(): void
    {
        $school = $this->provisioner->provision('Harmony High', 'hh-main-campus');

        $this->assertSame('hh-main-campus', $school->slug);
    }

    public function test_it_makes_generated_slugs_unique(): void
    {
        School::factory()->create(['slug' => 'harmony-high']);
        School::factory()->create(['slug' => 'harmony-high-2']);

        $school = $this->provisioner->provision('Harmony High');

        $this->assertSame('harmony-high-3', $school->slug);
    }

    public function test_it_falls_back_to_a_default_slug_for_symbol_only_names(): void
    {
        $school = $this->provisioner->provision('!!! ???');

        $this->assertNotSame('', $school->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $school->slug);
    }
}
