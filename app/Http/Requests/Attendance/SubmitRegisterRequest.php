<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceRegister;
use App\Support\Attendance\AttendanceAuthorizer;
use Illuminate\Contracts\Validation\Validator;

/**
 * Lock a register (`POST /attendance/{register}/submit`). Refused unless the
 * register is a draft the user may record for, it has at least one student, and
 * every student has been marked — an unmarked student is never counted as
 * present.
 */
class SubmitRegisterRequest extends AttendanceModuleRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $register = $this->register();

            if ($register === null) {
                return;
            }

            if (! $register->records()->exists()) {
                $validator->errors()->add('register', __('This register has no students.'));

                return;
            }

            if ($register->records()->unmarked()->exists()) {
                $validator->errors()->add('register', __('Mark every student before submitting.'));
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

    public function register(): ?AttendanceRegister
    {
        return $this->register ??= AttendanceRegister::query()->find($this->route('register'));
    }
}
