<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeeCategory;
use App\Models\FeePaymentAllocation;
use App\Models\StudentFeeCharge;

/**
 * The explicit cross-school isolation checklist from the M19 spec (section
 * 9) — each item gets its own named test, even where another Fees test file
 * already exercises the same behaviour as a side effect, so the checklist
 * itself is directly verifiable.
 */
class FeeTenantIsolationTest extends FeesTestCase
{
    public function test_school_a_cannot_read_school_bs_charges(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $this->chargeFor($schoolB, $studentB, ['description' => 'School B secret charge']);

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->get(route('fees.students.show', $studentB))->assertNotFound();

        // Even a direct model query from within school A's tenant context
        // must never see school B's row.
        $this->enterSchool($schoolA);
        $this->assertSame(0, StudentFeeCharge::count());
        $this->app->forgetScopedInstances();
    }

    public function test_school_a_cannot_create_a_charge_using_a_school_b_student(): void
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

    public function test_school_a_cannot_allocate_a_payment_to_a_school_bs_charge(): void
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
            'amount' => '5000.00', 'payment_date' => now()->toDateString(),
            'reference' => 'ISO-1', 'method' => 'cash',
            'allocations' => [$chargeB->id => '5000.00'],
        ]);

        $this->assertSame(0, FeePaymentAllocation::withoutGlobalScopes()->where('student_fee_charge_id', $chargeB->id)->count());
    }

    public function test_school_a_cannot_manipulate_school_bs_fee_categories(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolB);
        $categoryB = FeeCategory::factory()->create(['name' => 'School B Tuition']);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->patch(route('fees.categories.update', $categoryB), ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('School B Tuition', $categoryB->fresh()->name);
    }

    public function test_parent_cannot_access_another_parents_child(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $unrelatedChild = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $unrelatedChild);

        $this->actingAsRole($school, Role::Parent);
        $this->get(route('parent.fees.show', $unrelatedChild))->assertNotFound();
    }

    public function test_student_cannot_access_another_students_statement(): void
    {
        // The Student Portal fee route carries no {student} parameter at all
        // (mirrors every other Student Portal route since M17) — there is no
        // id to manipulate in the first place.
        $this->assertStringNotContainsString('{student}', route('student.fees.show'));
    }

    public function test_school_id_cannot_be_spoofed_on_a_charge_category_or_structure(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffold = $this->scaffold($schoolA);
        $student = $this->enrolledStudent($schoolA, $scaffold);

        $this->actingAsRole($schoolA, Role::Bursar);

        $this->post(route('fees.categories.store'), [
            'name' => 'Spoofed', 'school_id' => $schoolB->id,
        ]);
        $this->assertSame($schoolA->id, FeeCategory::first()->school_id);

        $this->post(route('fees.students.charges.store', $student), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'amount' => '1000.00',
            'school_id' => $schoolB->id,
        ]);
        $this->assertSame($schoolA->id, StudentFeeCharge::first()->school_id);
    }

    public function test_route_ids_cannot_create_idor_access_across_every_fee_resource(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $structureB = $this->structureIn($schoolB, $scaffoldB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $chargeB = $this->chargeFor($schoolB, $studentB);
        $paymentB = $this->paymentFor($schoolB, $studentB);

        $this->actingAsRole($schoolA, Role::Bursar);

        $this->get(route('fees.structures.edit', $structureB))->assertNotFound();
        $this->get(route('fees.students.show', $studentB))->assertNotFound();
        $this->post(route('fees.charges.waive', $chargeB))->assertNotFound();
        $this->post(route('fees.payments.void', $paymentB))->assertNotFound();
    }
}
