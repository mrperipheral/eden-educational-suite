<?php

namespace Tests\Feature\Fees;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Fees & Fee Management feature tests (M19,
 * `docs/fees.md`).
 *
 * Roles, from the M4/M19 bundles:
 *   - School Admin → everything.
 *   - Principal     → `fees.view` + `fees.report` (oversight, no write access).
 *   - Bursar        → `fees.manage` + `.record-payment` + `.adjust` + `.view` + `.report`.
 *   - Teacher/Staff/role-less → 403 (no financial access at all).
 *   - Parent/Student → their own/linked child's statement only, via `portal.parent`/`portal.student`.
 *
 * The Fees module is **on by default**; {@see self::disableFees()} turns it off.
 */
abstract class FeesTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function disableFees(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'fees', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    protected function actingAsRole(School $school, ?Role $role): User
    {
        $user = User::factory()->create();
        $user->joinSchool($school, $role);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /**
     * A current session (+ term) and a level (+ arm) — the applicability
     * context every fee structure/charge needs.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, category: FeeCategory}
     */
    protected function scaffold(School $school): array
    {
        $this->enterSchool($school);

        $session = AcademicSession::factory()->current()->create([
            'starts_on' => now()->subMonths(3)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);
        $period = $session->periods()->create([
            'name' => 'First Term',
            'starts_on' => now()->subMonths(3)->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $category = FeeCategory::factory()->create();

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'category');
    }

    /** A student currently enrolled in the scaffold's class. */
    protected function enrolledStudent(School $school, array $scaffold, array $studentAttributes = []): Student
    {
        $this->enterSchool($school);

        $student = Student::factory()->create($studentAttributes);
        $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id ?? null,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);

        $this->app->forgetScopedInstances();

        return $student;
    }

    protected function structureIn(School $school, array $scaffold, array $overrides = []): FeeStructure
    {
        $this->enterSchool($school);

        $structure = new FeeStructure(array_merge([
            'fee_category_id' => $scaffold['category']->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id ?? null,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id ?? null,
            'amount' => '5000.00',
        ], $overrides));
        $structure->created_by = User::factory()->create()->id;
        $structure->save();

        $this->app->forgetScopedInstances();

        return $structure;
    }

    protected function chargeFor(School $school, Student $student, array $overrides = []): StudentFeeCharge
    {
        $this->enterSchool($school);

        $charge = new StudentFeeCharge(array_merge([
            'student_id' => $student->id,
            'fee_category_id' => FeeCategory::factory()->create()->id,
            'academic_session_id' => AcademicSession::factory()->create()->id,
            'academic_level_id' => AcademicLevel::factory()->create()->id,
            'description' => 'Tuition',
            'amount' => '5000.00',
        ], $overrides));
        $charge->created_by = User::factory()->create()->id;
        $charge->save();

        $this->app->forgetScopedInstances();

        return $charge;
    }

    protected function paymentFor(School $school, Student $student, array $overrides = []): FeePayment
    {
        static $ref = 0;
        $this->enterSchool($school);

        $payment = new FeePayment(array_merge([
            'student_id' => $student->id,
            'amount' => '5000.00',
            'payment_date' => now()->toDateString(),
            'reference' => 'RCPT-'.str_pad((string) ++$ref, 6, '0', STR_PAD_LEFT),
            'method' => 'cash',
        ], $overrides));
        $payment->recorded_by = User::factory()->create()->id;
        $payment->save();

        $this->app->forgetScopedInstances();

        return $payment;
    }
}
