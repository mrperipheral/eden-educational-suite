<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRegister;
use App\Models\Examination;
use App\Models\FeeCategory;
use App\Models\GradingScheme;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Fees\FeePaymentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * M29 — Performance, Scalability & Reliability Validation.
 *
 * Seeds a large "Performance Academy A" (materially bigger than
 * `DatabaseSeeder`'s ~29-student demo school) plus a small "Performance
 * Academy B" for multi-school comparison, so the priority screens/reports
 * can be checked against realistic row counts. Deliberately **not** wired
 * into `DatabaseSeeder`/`migrate:fresh --seed` — this is a standalone,
 * manually-invoked fixture (`php artisan performance:seed-dataset`) kept
 * separate from normal demo data per `docs/performance-scalability.md`.
 *
 * High-volume tables (`attendance_records`, `student_results`,
 * `student_subject_results`) are bulk-inserted via `DB::table()->insert()`
 * in chunks — not one Eloquent `::create()` per row — purely so the command
 * itself finishes in a reasonable time; this mirrors the same bulk-insert
 * technique `App\Services\Results\ResultCompiler::persist()` already uses
 * in production code for the identical reason.
 */
class SeedPerformanceDataset extends Command
{
    protected $signature = 'performance:seed-dataset {--fresh : Delete any previously seeded performance schools first}';

    protected $description = 'M29: seed a large School A + a normal School B for empirical performance validation (not part of the normal demo seed)';

    public function handle(TenantContext $tenant, FeePaymentService $payments): int
    {
        if ($this->option('fresh')) {
            // A plain `School::delete()` cannot cascade cleanly:
            // `fee_payment_allocations.student_fee_charge_id` is
            // deliberately `restrictOnDelete()` (a real business rule — an
            // allocated charge is never deletable), which blocks MySQL's
            // cascade from reaching `student_fee_charges` even though
            // `fee_payment_allocations` itself also cascades from
            // `school_id`. Deleting the allocation rows first (satisfying
            // the restrict) lets the normal `schools` cascade handle
            // everything else.
            $ids = School::query()->whereIn('slug', ['performance-academy-a', 'performance-academy-b'])->pluck('id');
            foreach ($ids as $id) {
                DB::table('fee_payment_allocations')->where('school_id', $id)->delete();
                DB::table('schools')->where('id', $id)->delete();
            }
            // `User` is not school-owned/cascaded — the admin account this
            // command creates per school would otherwise collide on email
            // when re-seeding.
            DB::table('users')->where('email', 'like', 'admin@performance-academy-%.test')->delete();
            if ($ids->isNotEmpty()) {
                $this->info('Removed previously seeded performance schools.');
            }
        }

        $this->info('Seeding School A (large)...');
        $start = microtime(true);
        $schoolA = $this->seedSchool($tenant, $payments, 'Performance Academy A', 'performance-academy-a', arms: 5, studentsPerArm: 50, attendanceDays: 15, examAttemptRatio: 0.6, auditRows: 100);
        $this->info(sprintf('School A seeded in %.1fs (school_id=%d).', microtime(true) - $start, $schoolA->id));

        $this->info('Seeding School B (normal, for comparison)...');
        $start = microtime(true);
        $schoolB = $this->seedSchool($tenant, $payments, 'Performance Academy B', 'performance-academy-b', arms: 1, studentsPerArm: 15, attendanceDays: 5, examAttemptRatio: 0.5, auditRows: 10);
        $this->info(sprintf('School B seeded in %.1fs (school_id=%d).', microtime(true) - $start, $schoolB->id));

        $this->newLine();
        $this->table(['School', 'Students', 'Attendance records', 'Result rows', 'Fee charges'], [
            ['A (large)', 5 * 50, 5 * 50 * 15, 5 * 50, 5 * 50],
            ['B (normal)', 1 * 15, 1 * 15 * 5, 1 * 15, 1 * 15],
        ]);

        return self::SUCCESS;
    }

    private function seedSchool(TenantContext $tenant, FeePaymentService $payments, string $name, string $slug, int $arms, int $studentsPerArm, int $attendanceDays, float $examAttemptRatio, int $auditRows): School
    {
        $school = School::factory()->create(['name' => $name, 'slug' => $slug]);
        $tenant->set($school);

        $session = AcademicSession::factory()->current()->create([
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(7)->toDateString(),
        ]);
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(), 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create(['name' => 'Primary 1']);

        $subjects = Subject::factory()->count(5)->create();
        $level->subjects()->attach($subjects->pluck('id'), ['school_id' => $school->id]);

        $classwork = AssessmentCategory::factory()->create(['name' => 'Classwork', 'code' => 'CW', 'position' => 1]);
        $exam = AssessmentCategory::factory()->create(['name' => 'Exam', 'code' => 'EX', 'position' => 2]);
        $grading = GradingScheme::factory()->create(['name' => 'Standard']);
        $grading->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'remark' => 'Excellent', 'position' => 1]);
        $grading->grades()->create(['code' => 'B', 'min_percentage' => 50, 'max_percentage' => 69.99, 'remark' => 'Good', 'position' => 2]);
        $grading->grades()->create(['code' => 'F', 'min_percentage' => 0, 'max_percentage' => 49.99, 'remark' => 'Fail', 'position' => 3]);
        $weighting = ResultWeightingScheme::factory()->create(['name' => 'Standard']);
        $weighting->items()->create(['assessment_category_id' => $classwork->id, 'weight_percentage' => 40, 'position' => 1]);
        $weighting->items()->create(['assessment_category_id' => $exam->id, 'weight_percentage' => 60, 'position' => 2]);

        $admin = User::factory()->create(['email' => "admin@{$slug}.test", 'name' => "{$name} Admin"]);
        $admin->joinSchool($school, Role::SchoolAdmin);

        $feeCategory = FeeCategory::factory()->create(['name' => 'Tuition']);

        $auditRecorder = app(AuditRecorder::class);

        $now = now();
        $this->getOutput()->progressStart($arms);
        $firstArm = null;

        for ($armIndex = 0; $armIndex < $arms; $armIndex++) {
            $arm = $level->arms()->create(['name' => 'Arm '.($armIndex + 1), 'code' => 'A'.($armIndex + 1), 'position' => $armIndex + 1]);
            $firstArm ??= $arm;

            $teacher = Teacher::factory()->create();
            $teacher->assignments()->create([
                'academic_session_id' => $session->id, 'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                'subject_id' => $subjects->first()->id, 'started_on' => $session->starts_on->toDateString(),
            ]);

            // Students + enrollments (bulk — the roster itself is the basis
            // for every downstream row, so it stays a normal factory create
            // to get real, varied names for the report/export checks).
            $students = Student::factory()->count($studentsPerArm)->create();
            $enrollmentRows = $students->map(fn (Student $s) => [
                'school_id' => $school->id, 'student_id' => $s->id,
                'academic_session_id' => $session->id, 'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                'status' => EnrollmentStatus::Active->value, 'started_on' => $session->starts_on->toDateString(),
                'created_at' => $now, 'updated_at' => $now,
            ])->all();
            DB::table('enrollments')->insert($enrollmentRows);

            // Attendance: N submitted registers for this class, one record
            // per student per day — bulk-inserted (this is the single
            // largest table in the fixture).
            $attendanceRows = [];
            for ($day = 0; $day < $attendanceDays; $day++) {
                $register = AttendanceRegister::factory()->submitted()->create([
                    'academic_session_id' => $session->id, 'academic_period_id' => $period->id,
                    'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                    'attendance_date' => now()->subDays($attendanceDays - $day)->toDateString(),
                ]);
                foreach ($students as $student) {
                    $status = fake()->randomElement(['present', 'present', 'present', 'late', 'absent']);
                    $attendanceRows[] = [
                        'school_id' => $school->id, 'attendance_register_id' => $register->id, 'student_id' => $student->id,
                        'status' => $status, 'note' => null, 'recorded_at' => $now, 'recorded_by' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($attendanceRows, 500) as $chunk) {
                DB::table('attendance_records')->insert($chunk);
            }

            // Results: one locked run for this class, one StudentResult +
            // one StudentSubjectResult-per-subject per student — bulk.
            $run = ResultRun::factory()->locked()->create([
                'academic_session_id' => $session->id, 'academic_period_id' => $period->id,
                'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                'grading_scheme_id' => $grading->id, 'result_weighting_scheme_id' => $weighting->id,
            ]);
            $resultRows = [];
            $subjectResultRows = [];
            foreach ($students as $i => $student) {
                $avg = round(fake()->randomFloat(2, 35, 95), 2);
                $resultRows[] = [
                    'school_id' => $school->id, 'result_run_id' => $run->id, 'student_id' => $student->id,
                    'academic_session_id' => $session->id, 'academic_period_id' => $period->id,
                    'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                    'total_percentage' => $avg * count($subjects), 'average_percentage' => $avg,
                    'subject_count' => count($subjects), 'overall_grade_code_snapshot' => $avg >= 70 ? 'A' : ($avg >= 50 ? 'B' : 'F'),
                    'overall_grade_remark_snapshot' => null, 'position' => $i + 1, 'class_size' => $studentsPerArm,
                    'class_teacher_comment' => null, 'principal_comment' => null,
                    'days_school_opened' => $attendanceDays, 'days_present' => null, 'days_absent' => null, 'attendance_percentage' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                foreach ($subjects as $subject) {
                    $pct = round(fake()->randomFloat(2, 35, 95), 2);
                    $subjectResultRows[] = [
                        'school_id' => $school->id, 'result_run_id' => $run->id, 'student_id' => $student->id, 'subject_id' => $subject->id,
                        'academic_session_id' => $session->id, 'academic_period_id' => $period->id,
                        'academic_level_id' => $level->id, 'level_arm_id' => $arm->id,
                        'percentage' => $pct, 'grading_scheme_grade_id' => null,
                        'grade_code_snapshot' => $pct >= 70 ? 'A' : ($pct >= 50 ? 'B' : 'F'), 'grade_remark_snapshot' => null,
                        'subject_position' => null, 'is_adjusted' => false, 'adjusted_by' => null, 'adjusted_at' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            DB::table('student_results')->insert($resultRows);
            foreach (array_chunk($subjectResultRows, 500) as $chunk) {
                DB::table('student_subject_results')->insert($chunk);
            }

            // Fees: one charge per student, a payment for roughly half.
            foreach ($students as $student) {
                $charge = StudentFeeCharge::factory()->create([
                    'student_id' => $student->id, 'fee_category_id' => $feeCategory->id, 'created_by' => $admin->id,
                    'academic_session_id' => $session->id, 'academic_level_id' => $level->id, 'amount' => '45000.00',
                ]);
                if (fake()->boolean(50)) {
                    $payments->record($student, [
                        'amount' => '45000.00', 'payment_date' => now()->toDateString(),
                        'reference' => 'PERF-'.Str::upper(Str::random(10)), 'method' => PaymentMethod::Cash->value,
                        'payer_name' => null, 'payer_phone' => null, 'payer_email' => null, 'notes' => null,
                    ], [$charge->id => '45000.00'], $admin);
                }
            }

            $this->getOutput()->progressAdvance();
        }
        $this->getOutput()->progressFinish();

        // CBT: two examinations, attempts for a share of the whole roster.
        $allStudentIds = DB::table('students')->where('school_id', $school->id)->pluck('id');
        foreach (range(1, 2) as $n) {
            $examination = Examination::factory()->closed()->create([
                'academic_session_id' => $session->id, 'academic_period_id' => $period->id,
                'academic_level_id' => $level->id, 'level_arm_id' => $firstArm->id, 'subject_id' => $subjects->first()->id,
                'title' => "Performance Quiz {$n}", 'created_by' => $admin->id,
            ]);
            $attemptRows = [];
            foreach ($allStudentIds->random((int) ($allStudentIds->count() * $examAttemptRatio)) as $studentId) {
                $pct = round(fake()->randomFloat(2, 20, 100), 2);
                $startedAt = now()->subDays(2);
                $attemptRows[] = [
                    'school_id' => $school->id, 'examination_id' => $examination->id, 'student_id' => $studentId,
                    'started_at' => $startedAt, 'expires_at' => $startedAt->copy()->addMinutes(30), 'submitted_at' => $startedAt->copy()->addMinutes(20),
                    'status' => ExamAttemptStatus::Completed->value, 'score' => $pct, 'max_score' => 100, 'percentage' => $pct,
                    'passed' => $pct >= 50, 'auto_submitted' => false, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($attemptRows, 500) as $chunk) {
                DB::table('exam_attempts')->insert($chunk);
            }
        }

        // Audit log: a spread of realistic administrative events.
        for ($i = 0; $i < $auditRows; $i++) {
            $auditRecorder->record(
                event: 'student.created',
                summary: 'Performance-fixture audit entry #'.$i,
                actor: $admin,
            );
        }

        $tenant->forget();

        return $school;
    }
}
