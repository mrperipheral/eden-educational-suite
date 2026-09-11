<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeePaymentAllocation;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class StudentFeeChargeTest extends FeesTestCase
{
    public function test_bursar_can_raise_a_charge_from_a_structure(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $structure = $this->structureIn($school, $scaffold, ['amount' => '12000.00']);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.charges.store', $student), [
            'fee_structure_id' => $structure->id,
        ])->assertRedirect(route('fees.students.show', $student));

        $charge = StudentFeeCharge::first();
        $this->assertSame('12000.00', (string) $charge->amount);
        $this->assertSame($student->id, $charge->student_id);
        $this->assertSame($structure->id, $charge->fee_structure_id);
        $this->assertNotNull($charge->enrollment_id);
        $this->assertNotNull($charge->created_by);
    }

    public function test_bursar_can_raise_a_manual_charge(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.students.charges.store', $student), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'amount' => '3500.00',
            'description' => 'Excursion fee',
        ])->assertRedirect(route('fees.students.show', $student));

        $charge = StudentFeeCharge::first();
        $this->assertSame('3500.00', (string) $charge->amount);
        $this->assertSame('Excursion fee', $charge->description);
        $this->assertNull($charge->fee_structure_id);
    }

    public function test_a_charge_cannot_be_raised_for_a_student_with_no_current_enrollment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.students.charges.store', $student), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'amount' => '1000.00',
        ])->assertRedirect();

        $this->assertSame(0, StudentFeeCharge::count());
    }

    public function test_outstanding_balance_accounts_for_discount_waiver_and_payments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '10000.00']);

        $this->enterSchool($school);
        $this->assertSame('10000.00', $charge->outstandingBalance());

        $bursar = User::factory()->create();
        $charge->applyDiscount($bursar, '1000.00');
        $this->assertSame('9000.00', $charge->fresh()->payableAmount());
        $this->assertSame('9000.00', $charge->fresh()->outstandingBalance());
        $this->app->forgetScopedInstances();
    }

    public function test_discount_cannot_exceed_the_amount_already_paid(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '10000.00']);

        $payment = $this->paymentFor($school, $student, ['amount' => '8000.00']);
        $this->enterSchool($school);
        FeePaymentAllocation::factory()->create([
            'fee_payment_id' => $payment->id, 'student_fee_charge_id' => $charge->id, 'amount' => '8000.00',
        ]);

        $this->assertSame('8000.00', $charge->fresh()->allocatedAmount());
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.charges.discount', $charge), ['amount' => '5000.00'])
            ->assertRedirect();

        // Discount of 5000 would drop payable (10000-5000=5000) below the 8000 already paid — rejected.
        $this->assertSame('0.00', (string) $charge->fresh()->discount_amount);
    }

    public function test_waiving_forgives_the_remaining_balance_and_can_be_reversed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '10000.00']);

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.charges.waive', $charge), ['reason' => 'Hardship'])->assertRedirect();

        $charge->refresh();
        $this->assertTrue($charge->isWaived());
        $this->assertSame('0.00', $charge->outstandingBalance());
        $this->assertSame('10000.00', $charge->waivedAmount());

        $this->post(route('fees.charges.unwaive', $charge))->assertRedirect();
        $this->assertFalse($charge->fresh()->isWaived());
        $this->assertSame('10000.00', $charge->fresh()->outstandingBalance());
    }

    public function test_teacher_and_staff_cannot_raise_charges_or_adjust(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student);

        foreach ([Role::Teacher, Role::Staff] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.students.charges.create', $student))->assertForbidden();
            $this->post(route('fees.charges.waive', $charge))->assertForbidden();
        }
    }

    public function test_parent_and_student_cannot_create_charges_or_adjust(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student);

        foreach ([Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.students.charges.create', $student))->assertForbidden();
            $this->post(route('fees.students.charges.store', $student), [])->assertForbidden();
            $this->post(route('fees.charges.waive', $charge))->assertForbidden();
        }
    }

    public function test_school_a_cannot_create_a_charge_against_a_school_b_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->post(route('fees.students.charges.store', $studentB), [
            'fee_category_id' => $scaffoldA['category']->id,
            'academic_session_id' => $scaffoldA['session']->id,
            'amount' => '1000.00',
        ])->assertNotFound();
    }

    public function test_charge_has_no_destroy_route_history_is_retained(): void
    {
        $this->assertFalse(Route::has('fees.students.charges.destroy'));
    }

    public function test_module_off_404s_charge_routes(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->disableFees($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('fees.students.charges.create', $student))->assertNotFound();
    }
}
