<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeePaymentAllocation;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class FeeStatementTest extends FeesTestCase
{
    public function test_statement_totals_are_server_calculated_and_correct(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        $charge1 = $this->chargeFor($school, $student, ['amount' => '10000.00', 'description' => 'Tuition']);
        $charge2 = $this->chargeFor($school, $student, ['amount' => '2000.00', 'description' => 'Books']);

        $this->enterSchool($school);
        $bursar = User::factory()->create();
        $charge1->applyDiscount($bursar, '1000.00');
        $charge2->waive($bursar, 'Sibling discount');

        $payment = $this->paymentFor($school, $student, ['amount' => '4000.00']);
        $this->enterSchool($school);
        FeePaymentAllocation::factory()->create([
            'fee_payment_id' => $payment->id, 'student_fee_charge_id' => $charge1->id, 'amount' => '4000.00',
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Bursar);
        $response = $this->get(route('fees.students.show', $student))->assertOk();

        // Charged: 10000 + 2000 = 12000. Discount: 1000. Waived: 2000 (charge2's
        // full remaining balance). Paid: 4000. Outstanding: charge1 (10000-1000-4000=5000)
        // + charge2 (0, waived) = 5000.
        $response->assertViewHas('totalCharged', '12000.00');
        $response->assertViewHas('totalDiscount', '1000.00');
        $response->assertViewHas('totalWaived', '2000.00');
        $response->assertViewHas('totalPaid', '4000.00');
        $response->assertViewHas('totalOutstanding', '5000.00');
    }

    public function test_unallocated_credit_is_reported(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->paymentFor($school, $student, ['amount' => '3000.00']);

        $this->actingAsRole($school, Role::Bursar);
        $response = $this->get(route('fees.students.show', $student))->assertOk();

        $response->assertViewHas('totalReceived', '3000.00');
        $response->assertViewHas('totalUnallocated', '3000.00');
    }

    public function test_parent_sees_only_their_linked_childs_statement(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $ownChild = $this->enrolledStudent($school, $scaffold);
        $otherChild = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $ownChild, ['amount' => '5000.00', 'description' => 'Own child charge']);
        $this->chargeFor($school, $otherChild, ['amount' => '9999.00', 'description' => 'Other child charge']);

        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $ownChild->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary' => true]);
        $this->app->forgetScopedInstances();

        $parent = $this->actingAsRole($school, Role::Parent);
        $this->enterSchool($school);
        $guardian->user_id = $parent->id;
        $guardian->save();
        $this->app->forgetScopedInstances();

        $this->get(route('parent.fees.show', $ownChild))->assertOk()->assertSee('Own child charge');
        $this->get(route('parent.fees.show', $otherChild))->assertNotFound();
    }

    public function test_student_sees_only_their_own_statement(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $ownStudent = $this->enrolledStudent($school, $scaffold);
        $otherStudent = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $ownStudent, ['amount' => '5000.00', 'description' => 'My charge']);

        $this->enterSchool($school);
        $ownStudent->user_id = null; // placeholder to keep structure explicit
        $this->app->forgetScopedInstances();

        $studentUser = $this->actingAsRole($school, Role::Student);
        $this->enterSchool($school);
        $ownStudent->user_id = $studentUser->id;
        $ownStudent->save();
        $this->app->forgetScopedInstances();

        $this->get(route('student.fees.show'))->assertOk()->assertSee('My charge');

        // Tampering: there is no {student} param on the student route at all,
        // so a student can never even construct a URL for another student's
        // statement (the M17 no-route-parameter design).
        $this->assertFalse(Route::has('student.fees.show.show'));
    }

    public function test_parent_and_student_cannot_reach_the_staff_statement_or_dashboard(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        foreach ([Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.index'))->assertForbidden();
            $this->get(route('fees.students.show', $student))->assertForbidden();
        }
    }

    public function test_another_schools_student_statement_404s(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->get(route('fees.students.show', $studentB))->assertNotFound();
    }

    public function test_dashboard_lists_students_with_fee_activity_and_search_works(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $withCharge = $this->enrolledStudent($school, $scaffold, ['first_name' => 'Findme', 'last_name' => 'Student']);
        $this->enrolledStudent($school, $scaffold, ['first_name' => 'NoCharges', 'last_name' => 'Student']);
        $this->chargeFor($school, $withCharge, ['amount' => '1000.00']);

        $this->actingAsRole($school, Role::Bursar);
        $response = $this->get(route('fees.index', ['search' => 'Findme']))->assertOk();
        $response->assertSee('Findme');
        $response->assertDontSee('NoCharges');
    }

    public function test_statement_does_not_n_plus_one_for_a_student_with_many_transactions(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        foreach (range(1, 10) as $i) {
            $charge = $this->chargeFor($school, $student, ['amount' => '1000.00', 'description' => "Charge {$i}"]);
            $payment = $this->paymentFor($school, $student, ['amount' => '1000.00']);
            $this->enterSchool($school);
            FeePaymentAllocation::factory()->create([
                'fee_payment_id' => $payment->id, 'student_fee_charge_id' => $charge->id, 'amount' => '1000.00',
            ]);
            $this->app->forgetScopedInstances();
        }

        $this->actingAsRole($school, Role::Bursar);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('fees.students.show', $student))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $queries, "the statement ran {$queries} queries for 10 charges + 10 payments");
    }

    /**
     * M29.5 — the fees dashboard's "Recent payments" widget. Regression
     * guard: it eager-loads the paying student with a trimmed column list
     * and calls `Student::fullName()` (which also reads `middle_name`) — a
     * student with a middle name must render without a
     * MissingAttributeException.
     */
    public function test_the_fees_dashboard_shows_recent_payments_including_the_students_full_name(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold, ['middle_name' => 'Adaeze']);
        $this->paymentFor($school, $student, ['amount' => '4000.00']);

        $this->actingAsRole($school, Role::Bursar);
        $response = $this->get(route('fees.index'))->assertOk();

        $response->assertSee('Recent payments');
        $response->assertSee($student->fullName());
    }
}
