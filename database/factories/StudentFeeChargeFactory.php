<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentFeeCharge>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set explicitly via the factory definition.
 */
class StudentFeeChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_category_id' => FeeCategory::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'academic_level_id' => AcademicLevel::factory(),
            'created_by' => User::factory(),
            'description' => 'Tuition',
            'amount' => 5000,
        ];
    }
}
