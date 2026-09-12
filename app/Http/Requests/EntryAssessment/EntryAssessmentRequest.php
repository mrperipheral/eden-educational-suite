<?php

namespace App\Http\Requests\EntryAssessment;

use App\Enums\Permission;
use App\Models\EntryAssessment;
use App\Models\LevelArm;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Create or edit an {@see EntryAssessment} record. The coarse
 * gate is holding `placement.record` or `.manage`; the controller
 * re-checks the exact subject/level/arm via `App\Support\EntryAssessment\
 * EntryAssessmentAuthorizer::canManageFor()` (never trusted from this
 * request alone) — a Teacher without `.manage` may only record for a
 * subject/level (+ arm, if matching) they hold an active M11
 * `TeacherAssignment` for (M25, `docs/entry-placement-assessment.md`).
 *
 * `level_arm_id` is optional — the arm a candidate will join may not be
 * decided yet. `student_id` is optional and, when given, must already
 * belong to this school. `score` may not exceed `max_score` — checked in
 * {@see self::withValidator()}, since it is a cross-field invariant no
 * single column rule expresses.
 */
class EntryAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::EntryAssessmentRecord)
            || $this->user()?->hasPermission(Permission::EntryAssessmentManage)
            ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'student_id' => [
                'nullable', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'candidate_name' => ['required', 'string', 'max:150'],
            'admission_reference' => ['nullable', 'string', 'max:100'],
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
            'assessed_on' => ['required', 'date'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'result' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            $schoolId = app(TenantContext::class)->idOrFail();

            $levelId = $this->input('academic_level_id');
            $armId = $this->input('level_arm_id');

            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
                && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
                $errors->add('level_arm_id', __('That arm is not part of the selected level.'));
            }

            $subjectId = $this->input('subject_id');
            if ($levelId && $subjectId && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')
                && ! DB::table('level_subject')->where('school_id', $schoolId)->where('academic_level_id', $levelId)->where('subject_id', $subjectId)->exists()) {
                $errors->add('subject_id', __('That subject is not offered by the selected level.'));
            }

            if ($this->filled('score') && $this->filled('max_score') && ! $errors->has('score') && ! $errors->has('max_score')
                && bccomp((string) $this->input('score'), (string) $this->input('max_score'), 2) > 0) {
                $errors->add('score', __('The score cannot exceed the maximum score.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'student_id' => $this->filled('student_id') ? (int) $this->input('student_id') : null,
            'candidate_name' => $this->string('candidate_name')->trim()->value(),
            'admission_reference' => $this->filled('admission_reference') ? $this->string('admission_reference')->trim()->value() : null,
            'academic_level_id' => (int) $this->input('academic_level_id'),
            'level_arm_id' => $this->filled('level_arm_id') ? (int) $this->input('level_arm_id') : null,
            'subject_id' => (int) $this->input('subject_id'),
            'assessed_on' => $this->input('assessed_on'),
            'score' => $this->filled('score') ? (float) $this->input('score') : null,
            'max_score' => (float) $this->input('max_score'),
            'result' => $this->filled('result') ? $this->string('result')->trim()->value() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->value() : null,
        ];
    }
}
