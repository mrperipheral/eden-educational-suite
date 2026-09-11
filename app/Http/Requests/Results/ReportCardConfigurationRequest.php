<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\AcademicPeriod;
use App\Models\ReportCardConfiguration;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Create / edit a report-card configuration for a scope (school-wide, or
 * narrowed to a session / a session + period). Requires `result.manage`. See
 * `App\Models\ReportCardConfiguration::forScope()` for precedence.
 */
class ReportCardConfigurationRequest extends ResultsRequest
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

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'academic_session_id' => [
                'nullable', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'academic_period_id' => [
                'nullable', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
        ];

        foreach (ReportCardConfiguration::FIELDS as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('academic_period_id') && ! $this->filled('academic_session_id')) {
                $validator->errors()->add('academic_session_id', __('Select a session to narrow a configuration by term.'));

                return;
            }

            if ($this->filled('academic_period_id') && $this->filled('academic_session_id')
                && ! AcademicPeriod::query()->whereKey($this->input('academic_period_id'))
                    ->where('academic_session_id', $this->input('academic_session_id'))->exists()) {
                $validator->errors()->add('academic_period_id', __('That term is not part of the selected session.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['academic_session_id', 'academic_period_id'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'name' => $this->string('name')->trim()->value(),
            'academic_session_id' => $this->input('academic_session_id'),
            'academic_period_id' => $this->input('academic_period_id'),
        ];

        foreach (ReportCardConfiguration::FIELDS as $field) {
            $data[$field] = $this->boolean($field);
        }

        return $data;
    }
}
