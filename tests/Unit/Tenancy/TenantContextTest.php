<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\Exceptions\MissingTenantContextException;
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

    public function test_it_stores_and_clears_the_current_school_by_id(): void
    {
        $context = new TenantContext;

        $context->setId(42);
        $this->assertTrue($context->has());
        $this->assertSame(42, $context->id());
        $this->assertSame(42, $context->idOrFail());

        $context->forget();
        $this->assertFalse($context->has());
        $this->assertNull($context->id());
    }

    public function test_id_or_fail_throws_a_missing_context_exception(): void
    {
        $this->expectException(MissingTenantContextException::class);

        (new TenantContext)->idOrFail();
    }

    public function test_missing_context_exception_is_a_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, MissingTenantContextException::make());
    }

    public function test_run_without_scope_toggles_bypass_and_restores_it(): void
    {
        $context = new TenantContext;
        $this->assertFalse($context->isBypassed());

        $seen = $context->runWithoutScope(fn () => $context->isBypassed());

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

    public function test_run_without_scope_nests_correctly(): void
    {
        $context = new TenantContext;

        $context->runWithoutScope(function () use ($context) {
            $context->runWithoutScope(fn () => null);
            $this->assertTrue($context->isBypassed(), 'still bypassed after inner block');
        });

        $this->assertFalse($context->isBypassed());
    }

    public function test_run_without_scope_returns_the_callback_value(): void
    {
        $this->assertSame('ok', (new TenantContext)->runWithoutScope(fn () => 'ok'));
    }
}
