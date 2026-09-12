<?php

namespace Tests\Feature\Paystack;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\Guardian;
use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;

class PaymentInitiationTest extends PaystackTestCase
{
    public function test_an_authorised_parent_can_initiate_a_payment_for_their_child(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '10000.00']);
        $this->fakeInitialize('https://checkout.paystack.com/xyz');

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $response = $this->post(route('parent.fees.pay.store', $student), ['amount' => '5000.00']);

        $response->assertRedirect('https://checkout.paystack.com/xyz');
        $transaction = PaystackTransaction::first();
        $this->assertSame('5000.00', (string) $transaction->amount);
        $this->assertSame($student->id, $transaction->student_id);
        $this->assertSame($parent->id, $transaction->initiated_by);
        $this->assertNotNull($transaction->reference);
        $this->assertTrue($transaction->isPending());
    }

    public function test_an_authorised_student_can_initiate_a_payment_for_their_own_fees(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '10000.00']);
        $this->fakeInitialize('https://checkout.paystack.com/xyz');

        $studentUser = $this->linkedStudentUserFor($school, $student);
        $this->actingAs($studentUser);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('student.fees.pay.store'), ['amount' => '5000.00'])
            ->assertRedirect('https://checkout.paystack.com/xyz');

        $this->assertSame(1, PaystackTransaction::count());
    }

    public function test_a_parent_cannot_initiate_payment_for_an_unrelated_student(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '10000.00']);

        $this->actingAsRole($school, Role::Parent);
        $this->post(route('parent.fees.pay.store', $student), ['amount' => '5000.00'])->assertNotFound();

        $this->assertSame(0, PaystackTransaction::count());
    }

    public function test_initiation_fails_for_a_nonexistent_charge_context_with_no_outstanding_balance(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        // No charges at all — outstanding balance is zero.

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '100.00'])->assertRedirect();

        $this->assertSame(0, PaystackTransaction::count());
    }

    public function test_school_a_cannot_initiate_payment_against_a_school_b_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->configurePaystack($schoolA);
        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $this->chargeFor($schoolB, $studentB, ['amount' => '5000.00']);

        $this->actingAsRole($schoolA, Role::Parent);
        $this->post(route('parent.fees.pay.store', $studentB), ['amount' => '1000.00'])->assertNotFound();

        $this->assertSame(0, PaystackTransaction::count());
    }

    public function test_amount_cannot_exceed_the_outstanding_balance(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '3000.00']);

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '99999.00'])->assertRedirect();

        $this->assertSame(0, PaystackTransaction::count(), 'attempting to pay more than owed must not create a transaction');
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '3000.00']);

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->post(route('parent.fees.pay.store', $student), ['amount' => '-500'])->assertSessionHasErrors('amount');

        $this->assertSame(0, PaystackTransaction::count());
    }

    public function test_initiation_is_refused_when_paystack_is_disabled_for_the_school(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, enabled: false);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, PaystackTransaction::count());
    }

    public function test_initiation_never_leaves_an_orphan_transaction_when_paystack_api_fails(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $this->fakeInitializeFails();

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, PaystackTransaction::count(), 'a failed Paystack call must roll back the local row too');
    }

    public function test_each_initiation_generates_a_unique_reference(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '10000.00']);
        $this->fakeInitialize();

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00']);
        $this->post(route('parent.fees.pay.store', $student), ['amount' => '1000.00']);

        $references = PaystackTransaction::pluck('reference');
        $this->assertSame(2, $references->count());
        $this->assertSame(2, $references->unique()->count());
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

    private function linkedStudentUserFor($school, Student $student): User
    {
        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        return $user;
    }
}
