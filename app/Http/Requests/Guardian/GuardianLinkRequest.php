<?php

namespace App\Http\Requests\Guardian;

use App\Enums\GuardianRelationship;
use App\Models\GuardianStudent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create or edit a student ↔ guardian link. On create, `student_id` and
 * `guardian_id` are both checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)`, so a cross-school id fails with a
 * plain "invalid" message — no leak. The `(student_id, guardian_id)` pair must
 * not already exist.
 *
 * On edit, the `{link}` route id is resolved tenant-scoped in
 * {@see self::prepareForValidation()} — a cross-school link is a plain 404, never
 * a validation response — and only `relationship` / `is_primary` may change.
 */
class GuardianLinkRequest extends GuardianModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $rules = [
            'relationship' => ['required', new Enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ];

        if ($this->isCreate()) {
            $schoolId = $this->schoolId();

            $rules['student_id'] = [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ];
            $rules['guardian_id'] = [
                'required', 'integer',
                Rule::exists('guardians', 'id')->where('school_id', $schoolId),
                Rule::unique('guardian_student', 'guardian_id')
                    ->where(fn ($q) => $q->where('student_id', $this->input('student_id'))),
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // Editing an existing link: the `{link}` must be a row of the active
        // school. Resolved tenant-scoped *before* validation so a cross-school id
        // is a plain 404, never a validation response. Mirrors the M9 rule.
        $linkId = $this->route('link');
        if ($linkId !== null && ! GuardianStudent::query()->whereKey($linkId)->exists()) {
            abort(404);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.exists' => __('The selected student is invalid.'),
            'guardian_id.exists' => __('The selected guardian is invalid.'),
            'guardian_id.unique' => __('This guardian is already linked to that student.'),
        ];
    }

    public function isCreate(): bool
    {
        return $this->routeIs('guardians.links.store');
    }

    public function isPrimary(): bool
    {
        return $this->boolean('is_primary');
    }

    public function relationship(): GuardianRelationship
    {
        return GuardianRelationship::from($this->validated('relationship'));
    }
}
