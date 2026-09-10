<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TenantContextTest extends TestCase
{
    public function test_it_starts_empty(): void
    {
        $context = new TenantContext;

        $this->assertFalse($context->has());
        $this->assertNull($context->id());
    }

    public function test_it_stores_and_clears_the_current_school(): void
    {
        $context = new TenantContext;

        $context->set(42);
        $this->assertTrue($context->has());
        $this->assertSame(42, $context->id());
        $this->assertSame(42, $context->idOrFail());

        $context->forget();
        $this->assertFalse($context->has());
    }

    public function test_id_or_fail_throws_without_context(): void
    {
        $this->expectException(RuntimeException::class);

        (new TenantContext)->idOrFail();
    }

    public function test_run_without_scope_toggles_bypass_and_restores_it(): void
    {
        $context = new TenantContext;
        $this->assertFalse($context->isBypassed());

        $seen = $context->runWithoutScope(function () use ($context) {
            return $context->isBypassed();
        });

        $this->assertTrue($seen);
        $this->assertFalse($context->isBypassed(), 'bypass flag must be restored afterwards');
    }

    public function test_run_without_scope_restores_bypass_even_on_exception(): void
    {
        $context = new TenantContext;

        try {
            $context->runWithoutScope(function () {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse($context->isBypassed());
    }

    public function test_run_without_scope_returns_the_callback_value(): void
    {
        $this->assertSame('ok', (new TenantContext)->runWithoutScope(fn () => 'ok'));
    }
}
