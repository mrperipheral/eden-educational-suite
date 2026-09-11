<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\AcademicPeriod;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Create a result run — one class's compiled results for one term. Requires
 * `result.manage`. The academic context is fixed at creation; there is no
 * edit screen (a mis-created run in draft, with nothing compiled, can be
 * deleted and recreated).
 *
 * Validates: every id belongs to the active school; the arm belongs to the
 * level; the period belongs to the session; the grading / weighting schemes
 * are active; the weighting scheme's items sum to exactly 100; no run already
 * exists for this exact class + term (the DB unique index backs this with a
 * friendly message here).
 */
class ResultRunRequest extends ResultsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::ResultManage) ?? false;
    }

    /**
     * @return array<string, mixed>
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
            'grading_scheme_id' => [
                'required', 'integer',
                Rule::exists('grading_schemes', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'result_weighting_scheme_id' => [
                'required', 'integer',
                Rule::exists('result_weighting_schemes', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'ranking_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();

            $sessionId = $this->input('academic_session_id');
            $periodId = $this->input('academic_period_id');
            $levelId = $this->input('academic_level_id');
            $armId = $this->input('level_arm_id');

            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
                && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
                $errors->add('level_arm_id', __('That class is not part of the selected level.'));
            }

            if ($periodId && $sessionId && ! $errors->has('academic_period_id') && ! $errors->has('academic_session_id')
                && ! AcademicPeriod::query()->whereKey($periodId)->where('academic_session_id', $sessionId)->exists()) {
                $errors->add('academic_period_id', __('That term is not part of the selected session.'));
            }

            if (! $errors->has('result_weighting_scheme_id')) {
                $scheme = ResultWeightingScheme::query()->with('items')->find($this->input('result_weighting_scheme_id'));
                if ($scheme && abs($scheme->totalWeight() - 100.0) > 0.001) {
                    $errors->add('result_weighting_scheme_id', __('That weighting scheme totals :n%, not 100% — fix its weights first.', [
                        'n' => rtrim(rtrim(number_format($scheme->totalWeight(), 2), '0'), '.'),
                    ]));
                }
            }

            if ($errors->isEmpty() && $armId && $sessionId && $periodId
                && ResultRun::query()->where('level_arm_id', $armId)
                    ->where('academic_session_id', $sessionId)
                    ->where('academic_period_id', $periodId)
                    ->exists()) {
                $errors->add('level_arm_id', __('A result run already exists for that class and term.'));
            }
        });
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
            'level_arm_id.exists' => __('The selected class is invalid.'),
            'grading_scheme_id.exists' => __('The selected grading scheme is invalid.'),
            'result_weighting_scheme_id.exists' => __('The selected weighting scheme is invalid.'),
        ];
    }
}
