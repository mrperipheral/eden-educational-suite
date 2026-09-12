<?php

namespace App\Http\Requests\Promotion;

use App\Enums\Permission;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Run a bulk promotion. `promotion.manage`. Every academic id is checked to
 * belong to the active school with `Rule::exists(...)->where('school_id',
 * …)` (never trusted as-is), then the period ↔ session and arm ↔ level
 * pairs are checked for consistency — mirrors `App\Http\Requests\Student\
 * EnrollmentRequest` (M9). `student_ids` is re-validated authoritatively
 * (eligibility, duplicate target enrollment, tenant ownership) by
 * `App\Services\Promotion\PromotionService` — this request only catches the
 * obvious client-side mistakes early.
 */
class PromoteBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::PromotionManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'source_academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'source_academic_period_id' => [
                'nullable', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
            'source_academic_level_id' => [
                'required', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'source_level_arm_id' => [
                'nullable', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'target_academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'target_academic_level_id' => [
                'required', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'target_level_arm_id' => [
                'nullable', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();

            $sourceSessionId = $this->input('source_academic_session_id');
            $sourcePeriodId = $this->input('source_academic_period_id');
            if ($sourcePeriodId && $sourceSessionId && ! $errors->has('source_academic_period_id') && ! $errors->has('source_academic_session_id')) {
                $ok = AcademicPeriod::query()->whereKey($sourcePeriodId)->where('academic_session_id', $sourceSessionId)->exists();
                if (! $ok) {
                    $errors->add('source_academic_period_id', __('That term is not part of the selected source session.'));
                }
            }

            $sourceLevelId = $this->input('source_academic_level_id');
            $sourceArmId = $this->input('source_level_arm_id');
            if ($sourceArmId && $sourceLevelId && ! $errors->has('source_level_arm_id') && ! $errors->has('source_academic_level_id')) {
                $ok = LevelArm::query()->whereKey($sourceArmId)->where('academic_level_id', $sourceLevelId)->exists();
                if (! $ok) {
                    $errors->add('source_level_arm_id', __('That arm is not part of the selected source level.'));
                }
            }

            $targetLevelId = $this->input('target_academic_level_id');
            $targetArmId = $this->input('target_level_arm_id');
            if ($targetArmId && $targetLevelId && ! $errors->has('target_level_arm_id') && ! $errors->has('target_academic_level_id')) {
                $ok = LevelArm::query()->whereKey($targetArmId)->where('academic_level_id', $targetLevelId)->exists();
                if (! $ok) {
                    $errors->add('target_level_arm_id', __('That arm is not part of the selected target level.'));
                }
            }
        });
    }

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return array_map('intval', $this->input('student_ids', []));
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? $this->string('notes')->trim()->value() : null;
    }
}
