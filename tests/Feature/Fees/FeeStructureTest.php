<?php

namespace Tests\Feature\Fees;

use App\Enums\Role;
use App\Models\FeeStructure;

class FeeStructureTest extends FeesTestCase
{
    public function test_bursar_can_create_and_edit_a_structure(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.structures.store'), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'amount' => '15000.00',
            'is_mandatory' => '1',
        ])->assertRedirect(route('fees.structures.index'));

        $structure = FeeStructure::first();
        $this->assertSame('15000.00', (string) $structure->amount);
        $this->assertNotNull($structure->created_by);

        $this->patch(route('fees.structures.update', $structure), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'amount' => '20000.00',
            'is_active' => '1',
        ])->assertRedirect(route('fees.structures.index'));

        $this->assertSame('20000.00', (string) $structure->fresh()->amount);
    }

    public function test_period_must_belong_to_the_selected_session(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $otherScaffold = $this->scaffold($school);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.structures.store'), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $otherScaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'amount' => '1000.00',
        ])->assertSessionHasErrors('academic_period_id');
    }

    public function test_arm_must_belong_to_the_selected_level(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $otherScaffold = $this->scaffold($school);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.structures.store'), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $otherScaffold['arm']->id,
            'amount' => '1000.00',
        ])->assertSessionHasErrors('level_arm_id');
    }

    public function test_amount_must_be_positive(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->actingAsRole($school, Role::Bursar);

        $this->post(route('fees.structures.store'), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'amount' => '0',
        ])->assertSessionHasErrors('amount');

        $this->post(route('fees.structures.store'), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'amount' => '-500',
        ])->assertSessionHasErrors('amount');
    }

    public function test_scope_applicable_to_matches_the_whole_level_and_a_specific_arm(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);

        $wholeLevel = $this->structureIn($school, $scaffold, ['level_arm_id' => null]);
        $armSpecific = $this->structureIn($school, $scaffold, ['level_arm_id' => $scaffold['arm']->id]);

        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();

        $otherArmOnly = $this->structureIn($school, $scaffold, ['level_arm_id' => $otherArm->id]);

        $this->enterSchool($school);
        $applicable = FeeStructure::query()->applicableTo($scaffold['level']->id, $scaffold['arm']->id)->pluck('id');
        $this->app->forgetScopedInstances();

        $this->assertTrue($applicable->contains($wholeLevel->id));
        $this->assertTrue($applicable->contains($armSpecific->id));
        $this->assertFalse($applicable->contains($otherArmOnly->id));
    }

    public function test_editing_a_structures_amount_never_changes_an_existing_charge(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $structure = $this->structureIn($school, $scaffold, ['amount' => '5000.00']);
        $student = $this->enrolledStudent($school, $scaffold);

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.students.charges.store', $student), [
            'fee_structure_id' => $structure->id,
        ])->assertRedirect(route('fees.students.show', $student));

        $charge = $student->feeCharges()->first();
        $this->assertSame('5000.00', (string) $charge->amount);

        $this->patch(route('fees.structures.update', $structure), [
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'amount' => '99999.00',
            'is_active' => '1',
        ]);

        $this->assertSame('99999.00', (string) $structure->fresh()->amount);
        $this->assertSame('5000.00', (string) $charge->fresh()->amount, 'the historical charge must never change');
    }

    public function test_another_schools_structure_404s_and_cannot_be_referenced(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $structureB = $this->structureIn($schoolB, $scaffoldB);

        $this->actingAsRole($schoolA, Role::Bursar);
        $this->get(route('fees.structures.edit', $structureB))->assertNotFound();

        $scaffoldA = $this->scaffold($schoolA);
        $student = $this->enrolledStudent($schoolA, $scaffoldA);
        $this->post(route('fees.students.charges.store', $student), [
            'fee_structure_id' => $structureB->id,
        ])->assertSessionHasErrors('fee_structure_id');
    }

    public function test_teacher_staff_parent_and_student_cannot_manage_structures(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('fees.structures.index'))->assertForbidden();
            $this->get(route('fees.structures.create'))->assertForbidden();
        }
    }

    public function test_module_off_404s_structure_routes(): void
    {
        $school = $this->newSchool();
        $this->disableFees($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('fees.structures.index'))->assertNotFound();
    }
}
