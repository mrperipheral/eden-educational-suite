<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRegister;
use App\Support\Attendance\AttendanceAuthorizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

/**
 * Save marks for a register (`PATCH /attendance/{register}/records`).
 *
 * Only a **draft** register may be changed, and only by a user who may record
 * for its class. The submitted `records` map is keyed by student id; every key
 * must be a student already on this register's roster (so a cross-school or
 * non-eligible student id is rejected). The `{register}` route id is resolved
 * tenant-scoped — a cross-school id is a plain 404.
 */
class RecordAttendanceRequest extends AttendanceModuleRequest
{
    private ?AttendanceRegister $register = null;

    public function authorize(): bool
    {
        $register = $this->register();

        if ($register === null || $register->isLocked()) {
            return false;
        }

        return app(AttendanceAuthorizer::class)->canRecordForClass(
            $this->user(),
            $register->academic_level_id,
            $register->level_arm_id,
        );
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array'],
            'records.*.status' => ['nullable', new Enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:255'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rosterIds = $this->register()->records()->pluck('student_id')->all();

            foreach (array_keys((array) $this->input('records', [])) as $studentId) {
                if (! in_array((int) $studentId, $rosterIds, true)) {
                    $validator->errors()->add('records', __('One or more students are not part of this register.'));

                    return;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $registerId = $this->route('register');
        if ($registerId !== null && ! AttendanceRegister::query()->whereKey($registerId)->exists()) {
            abort(404);
        }
    }

    /**
     * The submitted marks, keyed by int student id.
     *
     * @return array<int, array{status: string|null, note: string|null}>
     */
    public function records(): array
    {
        $out = [];

        foreach ((array) $this->validated('records') as $studentId => $data) {
            $out[(int) $studentId] = [
                'status' => ($data['status'] ?? '') !== '' ? $data['status'] : null,
                'note' => ($data['note'] ?? '') !== '' ? trim((string) $data['note']) : null,
            ];
        }

        return $out;
    }

    public function register(): ?AttendanceRegister
    {
        return $this->register ??= AttendanceRegister::query()->find($this->route('register'));
    }
}
