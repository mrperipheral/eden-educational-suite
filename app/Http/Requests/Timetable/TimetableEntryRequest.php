<?php

namespace App\Http\Requests\Timetable;

use App\Enums\TeacherAssignmentStatus;
use App\Enums\Weekday;
use App\Models\LevelArm;
use App\Models\TeacherAssignment;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create or edit one scheduled lesson.
 *
 * Every id is checked to belong to the active school with
 * `Rule::exists(...)->where('school_id', …)` (a cross-school id → plain
 * "invalid", no leak). Then, server-side:
 *
 *   - the arm belongs to the chosen level;
 *   - the subject is offered by the level (`level_subject`);
 *   - an **active** M11 teacher assignment backs (teacher, subject, level) for
 *     the timetable's session (matching arm / period where the assignment pins
 *     them) — the timetable never re-implements teacher authorization;
 *   - `end_time` is after `start_time`;
 *   - no teacher / class / room is already booked for an overlapping lesson on
 *     the same weekday **in this timetable** (half-open interval — back-to-back
 *     lessons are allowed).
 *
 * Session / period are not on the entry — they come from the parent timetable,
 * which is resolved tenant-scoped in {@see self::prepareForValidation()}.
 */
class TimetableEntryRequest extends TimetableModuleRequest
{
    private ?Timetable $timetable = null;

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();

        return [
            'academic_level_id' => [
                'required', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'level_arm_id' => [
                'required', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
            'teacher_id' => [
                'required', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId),
            ],
            'weekday' => ['required', new Enum(Weekday::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            $timetable = $this->timetable();

            $levelId = $this->input('academic_level_id');
            $armId = $this->input('level_arm_id');
            $subjectId = $this->input('subject_id');
            $teacherId = $this->input('teacher_id');
            $start = (string) $this->input('start_time');
            $end = (string) $this->input('end_time');

            // -- arm ↔ level -------------------------------------------------
            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
                && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
                $errors->add('level_arm_id', __('That arm is not part of the selected level.'));
            }

            // -- subject offered by level ----------------------------------
            if ($subjectId && $levelId && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')
                && ! DB::table('level_subject')
                    ->where('school_id', $this->schoolId())
                    ->where('academic_level_id', $levelId)
                    ->where('subject_id', $subjectId)
                    ->exists()) {
                $errors->add('subject_id', __('That subject is not offered by the selected level.'));
            }

            // -- an active teacher assignment must back the pairing (M11) --
            if ($teacherId && $subjectId && $levelId && $timetable
                && ! $errors->has('teacher_id') && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')
                && ! $this->teacherIsAssigned((int) $teacherId, (int) $subjectId, (int) $levelId, $armId ? (int) $armId : null, $timetable)) {
                $errors->add('teacher_id', __('This teacher has no active assignment to teach that subject to this class. Add a teaching assignment first.'));
            }

            // -- end after start -----------------------------------------
            if (! $errors->has('start_time') && ! $errors->has('end_time') && $end <= $start) {
                $errors->add('end_time', __('The end time must be after the start time.'));

                return;
            }

            if ($errors->isNotEmpty() || ! $timetable) {
                return;
            }

            // -- overlap detection (half-open, same weekday, this timetable)
            $weekday = $this->weekdayInt();
            $room = $this->input('room');

            $clash = fn () => TimetableEntry::query()
                ->clashingWith($timetable->getKey(), $weekday, $start, $end)
                ->when($this->route('entry'), fn ($q, $id) => $q->whereKeyNot($id));

            if ($teacherId && $clash()->where('teacher_id', $teacherId)->exists()) {
                $errors->add('teacher_id', __('That teacher already has a lesson that overlaps this time.'));
            }

            if ($armId && $clash()->where('level_arm_id', $armId)->exists()) {
                $errors->add('level_arm_id', __('That class already has a lesson that overlaps this time.'));
            }

            if ($room !== null && $room !== '' && $clash()->where('room', $room)->exists()) {
                $errors->add('room', __('That room is already in use for an overlapping lesson.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // The parent timetable (route {timetable} on store, {entry}->timetable on
        // edit) must be a record of the active school — resolved tenant-scoped so
        // a cross-school route id is a plain 404, never a validation response.
        $timetableId = $this->route('timetable');
        if ($timetableId !== null && ! Timetable::query()->whereKey($timetableId)->exists()) {
            abort(404);
        }

        $entryId = $this->route('entry');
        if ($entryId !== null && ! TimetableEntry::query()->whereKey($entryId)->exists()) {
            abort(404);
        }

        $merge = [];

        if ($this->has('weekday') && is_numeric($this->input('weekday'))) {
            $merge['weekday'] = (int) $this->input('weekday');
        }

        if ($this->input('room') === '') {
            $merge['room'] = null;
        } elseif (is_string($this->input('room'))) {
            $merge['room'] = trim($this->input('room'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_level_id.exists' => __('The selected level is invalid.'),
            'level_arm_id.exists' => __('The selected arm is invalid.'),
            'subject_id.exists' => __('The selected subject is invalid.'),
            'teacher_id.exists' => __('The selected teacher is invalid.'),
            'start_time.date_format' => __('Use a 24-hour time like 08:30.'),
            'end_time.date_format' => __('Use a 24-hour time like 09:30.'),
        ];
    }

    /** The timetable this lesson belongs to — from the route on store, the row on edit. */
    public function timetable(): ?Timetable
    {
        if ($this->timetable !== null) {
            return $this->timetable;
        }

        $id = $this->route('timetable')
            ?? TimetableEntry::query()->whereKey($this->route('entry'))->value('timetable_id');

        return $this->timetable = $id ? Timetable::query()->find($id) : null;
    }

    public function weekdayInt(): int
    {
        return (int) $this->input('weekday');
    }

    private function teacherIsAssigned(int $teacherId, int $subjectId, int $levelId, ?int $armId, Timetable $timetable): bool
    {
        return TeacherAssignment::query()
            ->where('status', TeacherAssignmentStatus::Active->value)
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('academic_level_id', $levelId)
            ->where('academic_session_id', $timetable->academic_session_id)
            ->where(fn ($q) => $q->whereNull('level_arm_id')->when($armId, fn ($q) => $q->orWhere('level_arm_id', $armId)))
            ->where(fn ($q) => $q->whereNull('academic_period_id')
                ->when($timetable->academic_period_id, fn ($q) => $q->orWhere('academic_period_id', $timetable->academic_period_id)))
            ->exists();
    }
}
