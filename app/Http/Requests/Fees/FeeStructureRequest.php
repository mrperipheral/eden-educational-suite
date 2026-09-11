<?php

namespace App\Http\Requests\Fees;

use App\Enums\Permission;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / edit a fee structure. `fees.manage` only. Every academic id is
 * checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)` (never trusted as-is — a
 * cross-school id fails with a plain "invalid" message, no leak), then the
 * session ↔ period and level ↔ arm pairs are checked for consistency —
 * mirrors `App\Http\Requests\Student\EnrollmentRequest` (M9).
 */
class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::FeesManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'fee_category_id' => [
                'required', 'integer',
                Rule::exists('fee_categories', 'id')->where('school_id', $schoolId),
            ],
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
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
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

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'fee_category_id' => $this->integer('fee_category_id'),
            'academic_session_id' => $this->integer('academic_session_id'),
            'academic_period_id' => $this->input('academic_period_id') ?: null,
            'academic_level_id' => $this->integer('academic_level_id'),
            'level_arm_id' => $this->input('level_arm_id') ?: null,
            'amount' => $this->input('amount'),
            'is_mandatory' => $this->boolean('is_mandatory'),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
        ];

        if ($this->isMethod('patch')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
