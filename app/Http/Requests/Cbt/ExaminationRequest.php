<?php

namespace App\Http\Requests\Cbt;

use App\Enums\Permission;
use App\Enums\ResultReleaseMode;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Create or edit an examination. The coarse gate is holding either
 * `cbt.author` or `cbt.manage`; the controller re-checks the exact
 * class/subject via `App\Support\Cbt\CbtAuthorizer` (never trusted from
 * this request alone). Every academic id is checked to belong to the
 * active school with `Rule::exists(...)->where('school_id', …)`, then
 * period ↔ session, arm ↔ level and subject ↔ level are checked for
 * consistency — mirrors `App\Http\Requests\LearningMaterials\
 * LearningMaterialRequest` / `App\Http\Requests\Timetable\
 * TimetableEntryRequest`. Unlike learning materials, session/period/level/
 * arm/subject are **all required** — one exam is one class's exam, like an
 * `Assessment`, not a "whole level" broadcast.
 */
class ExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CbtAuthor)
            || $this->user()?->hasPermission(Permission::CbtManage)
            ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'academic_period_id' => [
                'required', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
            'academic_level_id' => [
                'required', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'level_arm_id' => [
                'required', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'pass_mark_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'result_release' => ['required', Rule::enum(ResultReleaseMode::class)],
            'result_release_at' => [
                Rule::requiredIf(fn () => $this->input('result_release') === ResultReleaseMode::Scheduled->value),
                'nullable', 'date',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            $schoolId = app(TenantContext::class)->idOrFail();

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

            $subjectId = $this->input('subject_id');
            if ($subjectId && $levelId && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')) {
                $ok = DB::table('level_subject')
                    ->where('school_id', $schoolId)
                    ->where('academic_level_id', $levelId)
                    ->where('subject_id', $subjectId)
                    ->exists();
                if (! $ok) {
                    $errors->add('subject_id', __('That subject is not offered by the selected level.'));
                }
            }

            $releaseAt = $this->input('result_release_at');
            $startsAt = $this->input('starts_at');
            if ($releaseAt && $startsAt && ! $errors->has('result_release_at') && ! $errors->has('starts_at')
                && strtotime($releaseAt) < strtotime($startsAt)) {
                $errors->add('result_release_at', __('The result release time cannot be before the examination starts.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'academic_session_id' => (int) $this->input('academic_session_id'),
            'academic_period_id' => (int) $this->input('academic_period_id'),
            'academic_level_id' => (int) $this->input('academic_level_id'),
            'level_arm_id' => (int) $this->input('level_arm_id'),
            'subject_id' => (int) $this->input('subject_id'),
            'title' => $this->string('title')->trim()->value(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'duration_minutes' => (int) $this->input('duration_minutes'),
            'starts_at' => $this->input('starts_at'),
            'ends_at' => $this->input('ends_at'),
            'pass_mark_percentage' => (float) $this->input('pass_mark_percentage'),
            'result_release' => $this->input('result_release'),
            'result_release_at' => $this->filled('result_release_at') ? $this->input('result_release_at') : null,
        ];
    }
}
