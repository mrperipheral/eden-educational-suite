<?php

namespace App\Http\Requests\Attendance;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\AttendanceRegister;
use App\Models\LevelArm;
use App\Models\Student;
use App\Support\Attendance\AttendanceAuthorizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Create an attendance register (registers themselves are not edited — only
 * their records).
 *
 * Every id is checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)` (a cross-school id → plain
 * "invalid", no leak). Then, server-side:
 *
 *   - the arm belongs to the chosen level;
 *   - the period belongs to the chosen session;
 *   - the date falls inside the session (and, if given, the period);
 *   - no register already exists for this class on this date;
 *   - at least one student is enrolled in that class on that date.
 *
 * `authorize()` additionally checks that the acting user may record for the
 * chosen class ({@see AttendanceAuthorizer}) — a teacher only for a class they
 * hold an active assignment for.
 */
class RegisterRequest extends AttendanceModuleRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && app(AttendanceAuthorizer::class)->canRecordForClass(
                $this->user(),
                (int) $this->input('academic_level_id'),
                (int) $this->input('level_arm_id'),
            );
    }

    /**
     * @return array<string, list<ValidationRule|string>>
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
                'nullable', 'integer',
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
            'attendance_date' => ['required', 'date', 'after:2000-01-01', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
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
            $date = (string) $this->input('attendance_date');

            // -- arm ↔ level -------------------------------------------------
            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
                && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
                $errors->add('level_arm_id', __('That arm is not part of the selected level.'));
            }

            // -- period ↔ session -----------------------------------------
            if ($periodId && $sessionId && ! $errors->has('academic_period_id') && ! $errors->has('academic_session_id')
                && ! AcademicPeriod::query()->whereKey($periodId)->where('academic_session_id', $sessionId)->exists()) {
                $errors->add('academic_period_id', __('That term is not part of the selected session.'));
            }

            if ($errors->isNotEmpty()) {
                return;
            }

            // -- date inside the academic context -----------------------
            $session = AcademicSession::query()->find($sessionId);
            if ($session && ($date < $session->starts_on->toDateString() || $date > $session->ends_on->toDateString())) {
                $errors->add('attendance_date', __('That date is outside the selected session.'));
            }
            if (! $errors->has('academic_period_id') && $periodId) {
                $period = AcademicPeriod::query()->find($periodId);
                if ($period && ($date < $period->starts_on->toDateString() || $date > $period->ends_on->toDateString())) {
                    $errors->add('attendance_date', __('That date is outside the selected term.'));
                }
            }

            // -- one register per class per day -------------------------
            if (! $errors->has('attendance_date')
                && AttendanceRegister::query()->where('level_arm_id', $armId)->whereDate('attendance_date', $date)->exists()) {
                $errors->add('attendance_date', __('A register already exists for that class on that date.'));
            }

            // -- at least one eligible student --------------------------
            if ($errors->isEmpty() && ! $this->classHasEligibleStudents((int) $sessionId, (int) $levelId, (int) $armId, $date)) {
                $errors->add('level_arm_id', __('No students are enrolled in that class on that date.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
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
            'academic_level_id.exists' => __('The selected level is invalid.'),
            'level_arm_id.exists' => __('The selected arm is invalid.'),
            'attendance_date.before_or_equal' => __('Attendance cannot be recorded for a future date.'),
        ];
    }

    private function classHasEligibleStudents(int $sessionId, int $levelId, int $armId, string $date): bool
    {
        return Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q
                ->where('academic_session_id', $sessionId)
                ->where('academic_level_id', $levelId)
                ->where('level_arm_id', $armId)
                ->where('started_on', '<=', $date)
                ->where(fn (Builder $q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $date)))
            ->exists();
    }
}
