<?php

namespace App\Http\Requests\Teacher;

use App\Enums\TeacherAssignmentStatus;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create or edit a teacher assignment. Every academic id is checked to belong to
 * the active school with `Rule::exists(...)->where('school_id', …)`, so a
 * cross-school id fails with a plain "invalid" message — no leak. The level ↔
 * arm and session ↔ period pairs are then checked for consistency, and a
 * duplicate **active** assignment for the same teacher / class / subject is
 * rejected.
 *
 * The `{teacher}` / `{assignment}` route ids are resolved tenant-scoped in
 * {@see self::prepareForValidation()} — a cross-school id is a plain 404, never a
 * validation response whose messages would be a weak oracle for another school's
 * academic ids. Mirrors the M9 enrollment rule.
 */
class TeacherAssignmentRequest extends TeacherModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'academic_period_id' => [
                'nullable', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
            'academic_level_id' => [
                'required', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'level_arm_id' => [
                'nullable', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
            'status' => ['required', new Enum(TeacherAssignmentStatus::class)],
            'started_on' => ['required', 'date', 'after:1950-01-01'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on', 'required_if:status,ended'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();

            $sessionId = $this->input('academic_session_id');
            $periodId = $this->input('academic_period_id');
            if ($periodId && $sessionId && ! $errors->has('academic_period_id') && ! $errors->has('academic_session_id')) {
                $ok = AcademicPeriod::query()->whereKey($periodId)->where('academic_session_id', $sessionId)->exists();
                if (! $ok) {
                    $errors->add('academic_period_id', __('That term is not part of the selected session.'));
                }
            }

            $levelId = $this->input('academic_level_id');
            $armId = $this->input('level_arm_id');
            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')) {
                $ok = LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists();
                if (! $ok) {
                    $errors->add('level_arm_id', __('That arm is not part of the selected level.'));
                }
            }

            if ($errors->isNotEmpty() || $this->input('status') !== TeacherAssignmentStatus::Active->value) {
                return;
            }

            // A teacher may teach many subjects to many classes, but the same
            // (teacher, subject, level, arm, session, period) must not be an
            // *active* assignment twice.
            $duplicate = TeacherAssignment::query()
                ->active()
                ->where('teacher_id', $this->teacherId())
                ->where('academic_session_id', $sessionId)
                ->where('academic_period_id', $periodId ?: null)
                ->where('academic_level_id', $levelId)
                ->where('level_arm_id', $armId ?: null)
                ->where('subject_id', $this->input('subject_id'))
                ->when($this->route('assignment'), fn ($q, $id) => $q->whereKeyNot($id))
                ->exists();

            if ($duplicate) {
                $errors->add('subject_id', __('This teacher already has an active assignment for that class and subject.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $teacherId = $this->route('teacher');
        if ($teacherId !== null && ! Teacher::query()->whereKey($teacherId)->exists()) {
            abort(404);
        }

        $assignmentId = $this->route('assignment');
        if ($assignmentId !== null && ! TeacherAssignment::query()->whereKey($assignmentId)->exists()) {
            abort(404);
        }

        foreach (['academic_period_id', 'level_arm_id', 'ended_on'] as $optional) {
            if ($this->input($optional) === '') {
                $this->merge([$optional => null]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_session_id.exists' => __('The selected session is invalid.'),
            'academic_period_id.exists' => __('The selected term is invalid.'),
            'academic_level_id.exists' => __('The selected level is invalid.'),
            'level_arm_id.exists' => __('The selected arm is invalid.'),
            'subject_id.exists' => __('The selected subject is invalid.'),
            'ended_on.required_if' => __('An end date is required when the assignment has ended.'),
        ];
    }

    /** The teacher this assignment belongs to — from the route on store, the row on edit. */
    private function teacherId(): ?int
    {
        return $this->route('teacher')
            ?? TeacherAssignment::query()->whereKey($this->route('assignment'))->value('teacher_id');
    }
}
