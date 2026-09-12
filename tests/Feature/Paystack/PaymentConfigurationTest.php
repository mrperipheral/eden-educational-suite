<?php

namespace Tests\Feature\Paystack;

use App\Enums\Role;

class PaymentConfigurationTest extends PaystackTestCase
{
    public function test_school_admin_can_configure_and_enable_paystack(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch(route('settings.school.payments.update'), [
            'paystack_enabled' => '1',
            'paystack_public_key' => 'pk_test_abc123',
            'paystack_secret_key' => 'sk_test_abc123',
            'paystack_test_mode' => '1',
        ])->assertRedirect(route('settings.school.payments.edit'));

        $this->enterSchool($school);
        $settings = $school->settings()->first();
        $this->assertTrue($settings->paystack_enabled);
        $this->assertSame('pk_test_abc123', $settings->paystack_public_key);
        $this->assertSame('sk_test_abc123', $settings->paystack_secret_key);
        $this->assertTrue($settings->paystackReady());
    }

    public function test_the_secret_key_is_encrypted_at_rest(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, true, 'sk_test_super_secret_value');

        $raw = \DB::table('school_settings')->where('school_id', $school->id)->value('paystack_secret_key');

        $this->assertStringNotContainsString('sk_test_super_secret_value', (string) $raw);
    }

    public function test_leaving_the_secret_field_blank_keeps_the_existing_key(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, true, 'sk_test_original');
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch(route('settings.school.payments.update'), [
            'paystack_enabled' => '1',
            'paystack_public_key' => 'pk_test_abc123',
            'paystack_secret_key' => '',
            'paystack_test_mode' => '1',
        ])->assertRedirect();

        $this->enterSchool($school);
        $this->assertSame('sk_test_original', $school->settings()->first()->paystack_secret_key);
    }

    public function test_the_secret_key_is_never_rendered_back_into_the_edit_form(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, true, 'sk_test_should_not_leak');
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->get(route('settings.school.payments.edit'));

        $response->assertOk()->assertDontSee('sk_test_should_not_leak');
    }

    public function test_only_school_settings_update_can_configure_payments(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Bursar, Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->patch(route('settings.school.payments.update'), [
                'paystack_enabled' => '1', 'paystack_public_key' => 'pk', 'paystack_secret_key' => 'sk',
            ])->assertForbidden();
        }
    }

    public function test_principal_has_read_only_access_to_payment_settings(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $this->actingAsRole($school, Role::Principal);

        $this->get(route('settings.school.payments.edit'))->assertOk();
        $this->patch(route('settings.school.payments.update'), ['paystack_enabled' => '0'])->assertForbidden();
    }

    public function test_paystack_ready_requires_enabled_and_both_keys(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $settings = $school->settings()->firstOrCreate([]);

        $this->assertFalse($settings->paystackReady());

        $settings->fill(['paystack_enabled' => true])->save();
        $this->assertFalse($settings->fresh()->paystackReady(), 'enabled alone is not enough');

        $settings->fill(['paystack_public_key' => 'pk_test_x', 'paystack_secret_key' => 'sk_test_x'])->save();
        $this->assertTrue($settings->fresh()->paystackReady());
    }

    public function test_a_school_is_never_required_to_enable_online_payment(): void
    {
        $school = $this->newSchool();
        // Never configured at all.
        $this->enterSchool($school);
        $settings = $school->settings()->firstOrCreate([]);
        $this->assertFalse($settings->paystackReady());
        $this->app->forgetScopedInstances();
    }
}
