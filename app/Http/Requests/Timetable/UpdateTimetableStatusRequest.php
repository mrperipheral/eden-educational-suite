<?php

namespace App\Http\Requests\Timetable;

use App\Enums\TimetableStatus;
use App\Models\Timetable;
use App\Support\Timetable\TimetableConflictScanner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

/**
 * Publish or unpublish a timetable. Publishing is refused unless the timetable
 * has at least one lesson and no scheduling clash — a published timetable is
 * always internally consistent. There is no approval workflow.
 */
class UpdateTimetableStatusRequest extends TimetableModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TimetableStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->status() !== TimetableStatus::Published) {
                return;
            }

            $timetable = Timetable::query()->find($this->route('timetable'));

            if ($timetable === null) {
                return; // handled by prepareForValidation / controller findOrFail
            }

            if (! $timetable->entries()->exists()) {
                $validator->errors()->add('status', __('Add at least one lesson before publishing.'));

                return;
            }

            if (app(TimetableConflictScanner::class)->pairs($timetable)->isNotEmpty()) {
                $validator->errors()->add('status', __('Resolve every scheduling clash before publishing.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $timetableId = $this->route('timetable');
        if ($timetableId !== null && ! Timetable::query()->whereKey($timetableId)->exists()) {
            abort(404);
        }
    }

    public function status(): TimetableStatus
    {
        return TimetableStatus::tryFrom((string) $this->input('status')) ?? TimetableStatus::Draft;
    }
}
