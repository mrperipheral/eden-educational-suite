<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\Student;
use App\Models\StudentSubjectResult;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSubjectResult>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related record must
 * belong to that same school. Pass explicit ids so the row lines up with a
 * scaffold's result run.
 */
class StudentSubjectResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_run_id' => ResultRun::factory(),
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'percentage' => 70,
        ];
    }
}
