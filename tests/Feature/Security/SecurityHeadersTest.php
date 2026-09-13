<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * M28 security hardening — `App\Http\Middleware\SecurityHeaders` (new,
 * registered globally in `bootstrap/app.php`) sets the response headers the
 * security review found completely absent from this application. Checked
 * on both a guest page and an authenticated one, since the middleware is
 * appended to the global stack, not a route-specific group.
 */
class SecurityHeadersTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_pages_carry_the_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_authenticated_pages_carry_the_security_headers(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $response = $this->get(route('dashboard'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_is_not_sent_over_a_plain_http_local_request(): void
    {
        // Local/test requests are never `->secure()`, so HSTS (which would
        // be actively misleading advice over plain HTTP) must be absent.
        $response = $this->get('/login');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
