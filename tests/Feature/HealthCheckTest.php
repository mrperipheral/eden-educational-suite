<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_framework_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_application_health_endpoint_reports_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'time', 'checks' => ['database']]);
    }

    public function test_application_health_endpoint_does_not_leak_environment(): void
    {
        $response = $this->getJson('/health');

        $this->assertArrayNotHasKey('env', $response->json());
        $this->assertArrayNotHasKey('debug', $response->json());
    }
}
