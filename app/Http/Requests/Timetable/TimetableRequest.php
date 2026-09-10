<?php

namespace App\Http\Requests\Timetable;

use App\Models\AcademicPeriod;
use App\Models\Timetable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Create or edit a timetable. The session is fixed at creation (its lessons
 * inherit it, so it may not change once the timetable exists); editing only
 * touches the name and the optional period.
 *
 * Every academic id is checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)`, so a cross-school id fails with a
 * plain "invalid" message — no leak. The `{timetable}` route id is resolved
 * tenant-scoped in {@see self::prepareForValidation()}.
 */
class TimetableRequest extends TimetableModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'academic_period_id' => [
                'nullable', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
        ];

        if ($this->isCreate()) {
            $rules['academic_session_id'] = [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            $periodId = $this->input('academic_period_id');

            if (! $periodId || $errors->has('academic_period_id')) {
                return;
            }

            if ($this->isCreate()) {
                $sessionId = $this->input('academic_session_id');
            } else {
                $sessionId = Timetable::query()->whereKey($this->route('timetable'))->value('academic_session_id');
            }

            if ($sessionId && ! $errors->has('academic_session_id')
                && ! AcademicPeriod::query()->whereKey($periodId)->where('academic_session_id', $sessionId)->exists()) {
                $errors->add('academic_period_id', __('That term is not part of the timetable\'s session.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $timetableId = $this->route('timetable');
        if ($timetableId !== null && ! Timetable::query()->whereKey($timetableId)->exists()) {
            abort(404);
        }

        if ($this->input('academic_period_id') === '') {
            $this->merge(['academic_period_id' => null]);
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
        ];
    }

    public function isCreate(): bool
    {
        return $this->route('timetable') === null;
    }
}
