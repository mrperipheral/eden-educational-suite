<?php

namespace App\Http\Requests\Student;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use App\Models\LevelArm;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create or edit an enrollment (a student's placement). Every academic id is
 * checked to belong to the active school with `Rule::exists(...)->where('school_id', …)`,
 * so a cross-school id fails with a plain "invalid" message — no leak. The
 * level ↔ arm and session ↔ period pairs are then checked for consistency.
 *
 * `make_active` (store only) decides whether this becomes the student's current
 * placement; the controller calls `Enrollment::makeActive()`.
 */
class EnrollmentRequest extends StudentModuleRequest
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
            'status' => ['required', new Enum(EnrollmentStatus::class)],
            'started_on' => ['required', 'date', 'after:1950-01-01'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on', 'required_unless:status,active'],
            'make_active' => ['sometimes', 'boolean'],
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
        });
    }

    protected function prepareForValidation(): void
    {
        // The parent (student on store, enrollment on update) must be a record of
        // the active school. Resolved tenant-scoped *before* validation so a
        // cross-school route id is a plain 404 — never a validation response
        // whose "invalid" messages would be a weak oracle for another school's
        // academic ids. Mirrors the M8 "resolve tenant-scoped" rule.
        $studentId = $this->route('student');
        if ($studentId !== null && ! Student::query()->whereKey($studentId)->exists()) {
            abort(404);
        }

        $enrollmentId = $this->route('enrollment');
        if ($enrollmentId !== null && ! Enrollment::query()->whereKey($enrollmentId)->exists()) {
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
            'ended_on.required_unless' => __('An end date is required unless the placement is active.'),
        ];
    }

    /** Store only — whether this enrollment should become the student's current placement. */
    public function shouldMakeActive(): bool
    {
        return $this->boolean('make_active', true);
    }
}
