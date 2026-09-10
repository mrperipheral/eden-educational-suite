<?php

namespace Database\Factories;

use App\Enums\TeacherAssignmentStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related academic record
 * must belong to that same school. Pass explicit ids when a test needs them to
 * line up with a specific scaffold.
 */
class TeacherAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => null,
            'subject_id' => Subject::factory(),
            'status' => TeacherAssignmentStatus::Active->value,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => null,
        ];
    }

    public function status(TeacherAssignmentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->value,
            'ended_on' => $status === TeacherAssignmentStatus::Active ? null : now()->toDateString(),
        ]);
    }
}
