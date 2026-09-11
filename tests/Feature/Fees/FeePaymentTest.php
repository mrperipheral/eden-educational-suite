<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\User;
use App\Services\Fees\FeePaymentService;
use Illuminate\Support\Facades\Route;

class FeePaymentTest extends FeesTestCase
{
    public function test_bursar_can_record_a_payment_with_allocation(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '10000.00']);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '6000.00',
            'payment_date' => now()->toDateString(),
            'reference' => 'RCPT-0001',
            'method' => 'cash',
            'allocations' => [$charge->id => '6000.00'],
        ])->assertRedirect(route('fees.students.show', $student));

        $payment = FeePayment::first();
        $this->assertSame('6000.00', (string) $payment->amount);
        $this->assertSame($student->id, $payment->student_id);
        $this->assertNotNull($payment->recorded_by);
        $this->assertSame('6000.00', $charge->fresh()->allocatedAmount());
        $this->assertSame('4000.00', $charge->fresh()->outstandingBalance());
    }

    public function test_payment_can_be_recorded_with_no_allocation_as_an_advance_credit(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '2000.00',
            'payment_date' => now()->toDateString(),
            'reference' => 'RCPT-0002',
            'method' => 'bank_transfer',
        ])->assertRedirect();

        $payment = FeePayment::first();
        $this->assertSame('2000.00', $payment->unallocatedAmount());
    }

    public function test_duplicate_reference_within_a_school_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->paymentFor($school, $student, ['reference' => 'DUPE-1']);

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '1000.00', 'payment_date' => now()->toDateString(),
            'reference' => 'DUPE-1', 'method' => 'cash',
        ])->assertSessionHasErrors('reference');

        $this->assertSame(1, FeePayment::count());
    }

    public function test_the_same_reference_is_allowed_in_a_different_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $studentA = $this->enrolledStudent($schoolA, $scaffoldA);
        $this->paymentFor($schoolA, $studentA, ['reference' => 'SHARED-REF']);

        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $this->actingAsRole($schoolB, Role::Bursar);

        $this->post(route('fees.students.payments.store', $studentB), [
            'amount' => '1000.00', 'payment_date' => now()->toDateString(),
            'reference' => 'SHARED-REF', 'method' => 'cash',
        ])->assertRedirect(route('fees.students.show', $studentB));

        $this->assertSame(2, FeePayment::withoutGlobalScopes()->where('reference', 'SHARED-REF')->count());
    }

    public function test_negative_or_zero_amount_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '0', 'payment_date' => now()->toDateString(), 'reference' => 'R1', 'method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '-100', 'payment_date' => now()->toDateString(), 'reference' => 'R2', 'method' => 'cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_allocation_cannot_exceed_the_payment_amount(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '10000.00']);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '3000.00', 'payment_date' => now()->toDateString(), 'reference' => 'R3', 'method' => 'cash',
            'allocations' => [$charge->id => '5000.00'],
        ])->assertSessionHasErrors('allocations');

        $this->assertSame(0, FeePayment::count());
    }

    public function test_allocation_cannot_exceed_a_charges_outstanding_balance(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '2000.00']);
        $this->actingAsRole($school, Role::Bursar);

        // Payment validation alone allows this (payment amount is 5000, allocation
        // 5000 <= payment), but the charge only owes 2000 — the service must reject it.
        $this->post(route('fees.students.payments.store', $student), [
            'amount' => '5000.00', 'payment_date' => now()->toDateString(), 'reference' => 'R4', 'method' => 'cash',
            'allocations' => [$charge->id => '5000.00'],
        ])->assertRedirect();

        $this->assertSame(0, FeePayment::count(), 'the whole transaction must roll back, not just the allocation');
    }

    public function test_service_rejects_allocating_to_a_charge_belonging_to_a_different_student(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $studentA = $this->enrolledStudent($school, $scaffold);
        $studentB = $this->enrolledStudent($school, $scaffold);
        $chargeB = $this->chargeFor($school, $studentB, ['amount' => '5000.00']);

        $payment = $this->paymentFor($school, $studentA, ['amount' => '5000.00']);
        $this->enterSchool($school);
        $bursar = User::factory()->create();

        $this->expectException(\DomainException::class);
        app(FeePaymentService::class)->allocate($payment, [$chargeB->id => '5000.00'], $bursar);
    }

    public function test_a_voided_payment_is_excluded_from_balances_but_the_record_is_kept(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '5000.00']);

        $payment = $this->paymentFor($school, $student, ['amount' => '5000.00']);
        $this->enterSchool($school);
        FeePaymentAllocation::factory()->create([
            'fee_payment_id' => $payment->id, 'student_fee_charge_id' => $charge->id, 'amount' => '5000.00',
        ]);

        $this->assertSame('0.00', $charge->fresh()->outstandingBalance());
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.payments.void', $payment), ['reason' => 'Bounced cheque'])->assertRedirect();

        $payment->refresh();
        $this->assertTrue($payment->isVoided());
        $this->assertSame('5000.00', $charge->fresh()->outstandingBalance());
        $this->assertDatabaseHas('fee_payments', ['id' => $payment->id, 'reference' => $payment->reference]);
    }

    public function test_only_fees_adjust_can_void_a_payment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $payment = $this->paymentFor($school, $student);

        $this->actingAsRole($school, Role::Teacher);
        $this->post(route('fees.payments.void', $payment))->assertForbidden();

        $this->actingAsRole($school, Role::Principal);
        $this->post(route('fees.payments.void', $payment))->assertForbidden();
    }

    public function test_only_fees_record_payment_holders_can_record_a_payment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        foreach ([Role::Teacher, Role::Staff, Role::Principal, Role::Parent, Role::Student, null] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.students.payments.create', $student))->assertForbidden();
        }
    }

    public function test_school_a_cannot_allocate_a_payment_to_a_school_b_charge(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $scaffoldB = $this->scaffold($schoolB);
        $studentA = $this->enrolledStudent($schoolA, $scaffoldA);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $chargeB = $this->chargeFor($schoolB, $studentB, ['amount' => '5000.00']);

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->post(route('fees.students.payments.store', $studentA), [
            'amount' => '5000.00', 'payment_date' => now()->toDateString(), 'reference' => 'CROSS-1', 'method' => 'cash',
            'allocations' => [$chargeB->id => '5000.00'],
        ])->assertRedirect();

        // The charge id belongs to another school's tenant scope entirely, so
        // it is invisible to school A's query — the service treats it as
        // "not found for this student" and rejects the whole payment.
        $this->assertSame(0, FeePayment::withoutGlobalScopes()->where('school_id', $schoolA->id)->count());
    }

    public function test_payment_has_no_destroy_or_edit_route_a_correction_is_a_void(): void
    {
        $this->assertFalse(Route::has('fees.students.payments.destroy'));
        $this->assertFalse(Route::has('fees.students.payments.update'));
    }

    public function test_module_off_404s_payment_routes(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->disableFees($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('fees.students.payments.create', $student))->assertNotFound();
    }
}
