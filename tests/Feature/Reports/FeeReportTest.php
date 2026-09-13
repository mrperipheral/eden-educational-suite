<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\FeeCategory;
use App\Models\FeePayment;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;
use App\Reports\FeeReport;
use App\Services\Fees\FeePaymentService;

/**
 * `FeeReport` (M27 §5) — every total via SQL `SUM()`, `bcmath` throughout,
 * `FeePayment::notVoided()` always applied. Reuses the exact M19
 * outstanding-balance invariant (an allocation never exceeds a charge's
 * own outstanding balance) rather than any GREATEST()/MAX() SQL.
 */
class FeeReportTest extends ReportsTestCase
{
    private function charge(School $school, Student $student, string $amount, array $overrides = []): StudentFeeCharge
    {
        $this->enterSchool($school);
        $charge = StudentFeeCharge::factory()->create(array_merge([
            'student_id' => $student->id,
            'fee_category_id' => FeeCategory::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'amount' => $amount,
        ], $overrides));
        $this->app->forgetScopedInstances();

        return $charge;
    }

    public function test_collection_summary_totals_are_correct(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $student = $students[0];

        $charge = $this->charge($school, $student, '10000.00', [
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
        ]);

        $this->enterSchool($school);
        $recorder = User::factory()->create();
        app(FeePaymentService::class)->record($student, [
            'amount' => '4000.00', 'payment_date' => now()->toDateString(), 'reference' => 'RCPT-TEST-1',
            'method' => 'cash', 'payer_name' => null, 'payer_phone' => null, 'payer_email' => null, 'notes' => null,
        ], [$charge->id => '4000.00'], $recorder);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(FeeReport::class)->collectionSummary([]);

        $this->assertSame('10000.00', $summary['total_charged']);
        $this->assertSame('4000.00', $summary['total_collected']);
        $this->assertSame('6000.00', $summary['total_outstanding']);
    }

    public function test_waived_charge_is_never_outstanding(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $student = $students[0];

        $charge = $this->charge($school, $student, '5000.00', [
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
        ]);

        $this->enterSchool($school);
        $admin = User::factory()->create();
        $charge->waive($admin, 'Scholarship');
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(FeeReport::class)->collectionSummary([]);

        $this->assertSame('5000.00', $summary['total_waived']);
        $this->assertSame('0.00', $summary['total_outstanding']);
    }

    public function test_outstanding_balances_only_lists_students_with_a_balance(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);

        $paidStudent = $students[0];
        $owingStudent = $students[1];

        $paidCharge = $this->charge($school, $paidStudent, '2000.00', [
            'academic_session_id' => $scaffold['session']->id, 'academic_level_id' => $scaffold['level']->id,
        ]);
        $this->charge($school, $owingStudent, '3000.00', [
            'academic_session_id' => $scaffold['session']->id, 'academic_level_id' => $scaffold['level']->id,
        ]);

        $this->enterSchool($school);
        $recorder = User::factory()->create();
        app(FeePaymentService::class)->record($paidStudent, [
            'amount' => '2000.00', 'payment_date' => now()->toDateString(), 'reference' => 'RCPT-TEST-2',
            'method' => 'cash', 'payer_name' => null, 'payer_phone' => null, 'payer_email' => null, 'notes' => null,
        ], [$paidCharge->id => '2000.00'], $recorder);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(FeeReport::class)->outstandingBalances([]);

        $this->assertSame(1, $page->total());
        $this->assertSame($owingStudent->id, $page->items()[0]->student_id);
        $this->assertSame('3000.00', $page->items()[0]->outstanding_balance);
    }

    public function test_payment_activity_lists_payments_and_filters_by_method(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $student = $students[0];

        $this->enterSchool($school);
        FeePayment::factory()->create(['student_id' => $student->id, 'method' => 'cash', 'reference' => 'RCPT-A']);
        FeePayment::factory()->create(['student_id' => $student->id, 'method' => 'paystack', 'reference' => 'RCPT-B']);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $all = app(FeeReport::class)->paymentActivity([]);
        $this->assertSame(2, $all->total());

        $cashOnly = app(FeeReport::class)->paymentActivity(['method' => 'cash']);
        $this->assertSame(1, $cashOnly->total());
    }

    public function test_fee_report_never_exposes_paystack_secret_keys(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->enterSchool($school);
        FeePayment::factory()->create(['student_id' => $students[0]->id, 'method' => 'paystack']);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(FeeReport::class)->paymentActivity([]);

        $this->assertArrayNotHasKey('gateway_response', $page->items()[0]->getAttributes());
        $this->assertArrayNotHasKey('secret_key', $page->items()[0]->getAttributes());
    }
}
