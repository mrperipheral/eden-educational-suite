<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

class TenantContextBindingTest extends TestCase
{
    public function test_tenant_context_is_a_single_instance_per_request(): void
    {
        $a = $this->app->make(TenantContext::class);
        $b = $this->app->make(TenantContext::class);

        $this->assertSame($a, $b);
    }
}
