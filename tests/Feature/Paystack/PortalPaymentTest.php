<?php

namespace Tests\Feature\Paystack;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\Guardian;
use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use App\Services\Paystack\PaymentVerificationService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PortalPaymentTest extends PaystackTestCase
{
    public function test_parent_cannot_view_or_pay_for_another_parents_child(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $otherChild = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $otherChild, ['amount' => '5000.00']);

        $this->actingAsRole($school, Role::Parent);

        $this->get(route('parent.fees.pay.create', $otherChild))->assertNotFound();
        $this->post(route('parent.fees.pay.store', $otherChild), ['amount' => '1000.00'])->assertNotFound();
    }

    public function test_student_cannot_access_another_students_payment_initiation_or_receipt(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $otherStudent = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $otherStudent, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $otherStudent, '5000.00');
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        // A different student, not linked to $otherStudent in any way.
        $me = $this->enrolledStudent($school, $scaffold);
        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $me->user_id = $user->id;
        $me->save();
        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        // Manipulating the callback reference to point at someone else's
        // transaction must never render their receipt.
        $this->get(route('student.fees.pay.callback', ['reference' => $transaction->reference]))->assertNotFound();
    }

    public function test_a_parent_cannot_view_another_familys_receipt_by_guessing_a_reference(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $victimChild = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $victimChild, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $victimChild, '5000.00');
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);
        app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        // An unrelated parent with their own, different child.
        $attackerChild = $this->enrolledStudent($school, $scaffold);
        $attacker = $this->linkedParentFor($school, $attackerChild);
        $this->actingAs($attacker);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('parent.fees.pay.callback', [
            'student' => $attackerChild->id,
            'reference' => $transaction->reference,
        ]))->assertNotFound();
    }

    public function test_module_off_404s_online_payment_routes(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->disableFees($school);

        $parent = $this->linkedParentFor($school, $student);
        $this->actingAs($parent);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('parent.fees.pay.create', $student))->assertNotFound();
    }

    public function test_teacher_and_staff_have_no_access_to_online_payment_portal_routes(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        foreach ([Role::Teacher, Role::Staff, Role::Bursar] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('parent.fees.pay.create', $student))->assertForbidden();
        }
    }

    public function test_paying_online_never_lets_a_parent_create_a_charge_or_adjust_a_balance(): void
    {
        $this->assertFalse(Route::has('parent.fees.charges.store'));
        $this->assertFalse(Route::has('parent.fees.payments.void'));
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

    private function pendingTransaction($school, $student, string $amount): PaystackTransaction
    {
        $this->enterSchool($school);
        $user = User::factory()->create();
        $transaction = new PaystackTransaction([
            'student_id' => $student->id,
            'reference' => 'PSK-'.Str::upper(Str::random(20)),
            'amount' => $amount,
            'currency' => 'NGN',
        ]);
        $transaction->initiated_by = $user->id;
        $transaction->save();
        $this->app->forgetScopedInstances();

        return $transaction;
    }
}
