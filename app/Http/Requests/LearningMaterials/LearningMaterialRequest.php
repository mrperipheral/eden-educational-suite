<?php

namespace App\Http\Requests\LearningMaterials;

use App\Enums\LearningMaterialType;
use App\Enums\Permission;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Upload a learning material. The coarse gate is holding either
 * `material.upload` or `.manage`; the controller re-checks the
 * exact class/subject via `App\Support\LearningMaterials\
 * LearningMaterialAuthorizer` (never trusted from this request alone).
 * Every academic id is checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)`, then period ↔ session,
 * arm ↔ level and subject ↔ level are checked for consistency — mirrors
 * `App\Http\Requests\Promotion\PromoteBatchRequest` /
 * `App\Http\Requests\Timetable\TimetableEntryRequest`.
 *
 * The `file` rule's `mimetypes:` whitelist is built from
 * `LearningMaterialType::enabledMimeTypes()` — **video is never in it**,
 * so a tampered/manual request carrying a video file is rejected here,
 * server-side, regardless of what the UI offers.
 */
class LearningMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::LearningMaterialUpload)
            || $this->user()?->hasPermission(Permission::LearningMaterialManage)
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
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', LearningMaterialType::enabledMimeTypes()),
                'max:'.(20 * 1024), // 20MB, in kilobytes
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
        });
    }

    public function title(): string
    {
        return $this->string('title')->trim()->value();
    }

    public function description(): ?string
    {
        return $this->filled('description') ? $this->string('description')->trim()->value() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'academic_session_id' => (int) $this->input('academic_session_id'),
            'academic_period_id' => $this->filled('academic_period_id') ? (int) $this->input('academic_period_id') : null,
            'academic_level_id' => (int) $this->input('academic_level_id'),
            'level_arm_id' => $this->filled('level_arm_id') ? (int) $this->input('level_arm_id') : null,
            'subject_id' => (int) $this->input('subject_id'),
            'title' => $this->title(),
            'description' => $this->description(),
        ];
    }
}
