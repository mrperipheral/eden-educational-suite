<?php

namespace App\Http\Requests\Academic;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit an academic period (term / semester). Names and order are
 * unique *within the session*, the end date must be after the start, and the
 * number of periods is entirely up to the school.
 */
class PeriodRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $sessionId = $this->sessionId();
        $periodId = $this->route('period');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('academic_periods', 'name')
                    ->where('academic_session_id', $sessionId)
                    ->ignore($periodId),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'position' => [
                'required', 'integer', 'min:1', 'max:50',
                Rule::unique('academic_periods', 'position')
                    ->where('academic_session_id', $sessionId)
                    ->ignore($periodId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This session already has a period with that name.'),
            'position.unique' => __('Another period in this session already uses that order number.'),
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }

    /**
     * The session these rules scope to: the route segment on store, or the
     * edited period's own session on update — resolved **tenant-scoped**, so a
     * session id from another school comes back null and the controller's
     * `findOrFail` turns the request into a 404 rather than a validation error.
     */
    private function sessionId(): ?int
    {
        if ($this->route('session') !== null) {
            return AcademicSession::query()->whereKey($this->route('session'))->value('id');
        }

        return AcademicPeriod::query()->find($this->route('period'))?->academic_session_id;
    }
}
