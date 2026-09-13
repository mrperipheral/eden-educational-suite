<?php

namespace Tests\Feature\Security;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Tests\Feature\Paystack\PaystackTestCase;

/**
 * M28 security hardening — CSV/report exports and Paystack payment
 * initiation are the two classes of route the security review found
 * completely unthrottled (only login/password-reset/registration had any
 * rate limiting before this milestone). Proves the new named limiters
 * (`exports`, `payment-initiation`, registered in
 * `AppServiceProvider::boot()`) actually reject once their limit is
 * exceeded — not just that the `throttle:` middleware is attached.
 */
class RateLimitingTest extends PaystackTestCase
{
    public function test_report_export_routes_are_rate_limited(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // The limiter allows 20/minute; the 21st request in the same window
        // must be rejected rather than silently allowed to keep hammering
        // an export query.
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('audit-log.export'))->assertOk();
        }

        $this->get(route('audit-log.export'))->assertStatus(429);
    }

    public function test_payment_initiation_route_is_rate_limited(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '1000000.00']);
        $this->fakeInitialize();

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        // The limiter allows 10/minute; the 11th initiation attempt in the
        // same window must be rejected.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00']);
        }

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00'])->assertStatus(429);
    }

    private function linkedParentFor($school, Student $student): User
    {
        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Parent);
        $guardian = Guardian::factory()->create();
        $guardian->user_id = $user->id;
        $guardian->save();
        $student->guardianLinks()->create([
            'guardian_id' => $guardian->id,
            'relationship' => GuardianRelationship::Mother->value,
            'is_primary' => true,
        ]);
        $this->app->forgetScopedInstances();

        return $user;
    }
}
