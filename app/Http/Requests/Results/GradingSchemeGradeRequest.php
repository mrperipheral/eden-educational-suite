<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\GradingScheme;
use App\Models\GradingSchemeGrade;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Add / edit one grade band on a {@see GradingScheme}. Requires
 * `result.manage`. `code` is unique within the scheme; `min_percentage` /
 * `max_percentage` must sit within 0-100, `min <= max`, and must not overlap
 * another **active** band in the same scheme.
 */
class GradingSchemeGradeRequest extends ResultsRequest
{
    private ?GradingScheme $scheme = null;

    public function authorize(): bool
    {
        return $this->scheme() !== null && ($this->user()?->hasPermission(Permission::ResultManage) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $gradeId = $this->route('grade');

        return [
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('grading_scheme_grades', 'code')
                    ->where('grading_scheme_id', $this->scheme()?->id)
                    ->ignore($gradeId),
            ],
            'min_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'max_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2', 'gte:min_percentage'],
            'remark' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $min = (float) $this->input('min_percentage');
            $max = (float) $this->input('max_percentage');
            $gradeId = $this->route('grade');

            $overlaps = $this->scheme()->grades()
                ->active()
                ->when($gradeId, fn ($q) => $q->whereKeyNot($gradeId))
                ->where(fn ($q) => $q
                    ->whereBetween('min_percentage', [$min, $max])
                    ->orWhereBetween('max_percentage', [$min, $max])
                    ->orWhere(fn ($q) => $q->where('min_percentage', '<=', $min)->where('max_percentage', '>=', $max)))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('min_percentage', __('That range overlaps another active grade in this scheme.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $gradeId = $this->route('grade');
        if ($gradeId !== null) {
            $grade = GradingSchemeGrade::query()->find($gradeId);
            abort_if($grade === null, 404);
            $this->scheme = $grade->scheme;
        } else {
            $this->scheme = GradingScheme::query()->find($this->route('scheme'));
            abort_if($this->scheme === null, 404);
        }

        if ($this->input('code') !== null) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'code' => $this->input('code'),
            'min_percentage' => $this->input('min_percentage'),
            'max_percentage' => $this->input('max_percentage'),
            'remark' => $this->filled('remark') ? $this->string('remark')->trim()->value() : null,
            'position' => (int) ($this->input('position') ?: 0),
        ];

        if ($this->isMethod('patch')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }

    public function scheme(): ?GradingScheme
    {
        return $this->scheme;
    }
}
