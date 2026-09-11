<?php

namespace Tests\Feature\Guardian;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;

/**
 * The clean domain boundaries M10 preserves:
 *   School → Guardians · Guardian ↔ Students · Student → Guardians
 * and nothing else (no teacher / attendance / fee relationships). `user()`
 * (M16, `docs/parent-portal.md`) is a deliberate, later exception — the
 * Parent Portal login link — not a boundary violation.
 */
class GuardianStructureTest extends GuardianTestCase
{
    public function test_school_owns_guardians_and_students_and_guardians_link_both_ways(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);

        $students = Student::factory()->count(2)->create();
        $guardian = Guardian::factory()->create();

        foreach ($students as $student) {
            $student->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'legal_guardian']);
        }

        $this->assertSame(1, $school->guardians()->count());
        $this->assertSame(2, $school->students()->count());
        $this->assertSame(2, $guardian->students()->count());
        $this->assertSame(1, $students->first()->guardians()->count());
        $this->assertTrue($students->first()->guardians->first()->is($guardian));
        $this->assertSame('legal_guardian', $students->first()->guardians->first()->pivot->relationship);
    }

    public function test_the_link_table_carries_school_and_both_parents_but_no_future_module_columns(): void
    {
        $columns = Schema::getColumnListing('guardian_student');

        sort($columns);
        $this->assertSame(
            ['created_at', 'guardian_id', 'id', 'is_primary', 'relationship', 'school_id', 'student_id', 'updated_at'],
            $columns,
        );
    }

    public function test_guardian_model_exposes_no_forbidden_relationships(): void
    {
        $guardian = new Guardian;

        foreach (['teachers', 'attendance', 'fees', 'invoices', 'payments'] as $relation) {
            $this->assertFalse(method_exists($guardian, $relation), "Guardian should not define `{$relation}()`");
        }
    }

    public function test_primary_designation_is_scoped_per_student_not_per_guardian(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);

        $guardian = Guardian::factory()->create();
        $s1 = Student::factory()->create();
        $s2 = Student::factory()->create();

        // The same guardian is primary for two different students — allowed.
        $l1 = $s1->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary' => true]);
        $l2 = $s2->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary' => true]);
        $l2->makePrimary();

        $this->assertTrue($l1->fresh()->is_primary);
        $this->assertTrue($l2->fresh()->is_primary);
    }
}
