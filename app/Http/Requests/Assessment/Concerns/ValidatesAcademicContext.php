<?php

namespace App\Http\Requests\Assessment\Concerns;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared academic-context validation for assessments and assignments:
 *
 *   - every id belongs to the active school (`Rule::exists(...)->where('school_id')`
 *     → generic "invalid", no leak);
 *   - the arm belongs to the level;
 *   - the period belongs to the session;
 *   - the subject is offered by the level (`level_subject`);
 *   - the given date falls inside the session **and** the period.
 *
 * Nothing here assumes a term count or a school calendar — the bounds come from
 * the school's own {@see AcademicSession} / {@see AcademicPeriod} rows.
 */
trait ValidatesAcademicContext
{
    /**
     * @return array<string, mixed>
     */
    protected function academicContextRules(int $schoolId): array
    {
        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'academic_period_id' => [
                'required', 'integer',
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
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function academicContextMessages(): array
    {
        return [
            'academic_session_id.exists' => __('The selected session is invalid.'),
            'academic_period_id.exists' => __('The selected term is invalid.'),
            'academic_level_id.exists' => __('The selected level is invalid.'),
            'level_arm_id.exists' => __('The selected class is invalid.'),
            'subject_id.exists' => __('The selected subject is invalid.'),
        ];
    }

    /**
     * Run the cross-field checks. `$dateField` is the request key holding the
     * date that must sit inside the academic context.
     */
    protected function validateAcademicContext(Validator $validator, string $dateField): void
    {
        $errors = $validator->errors();

        $sessionId = $this->input('academic_session_id');
        $periodId = $this->input('academic_period_id');
        $levelId = $this->input('academic_level_id');
        $armId = $this->input('level_arm_id');
        $subjectId = $this->input('subject_id');
        $date = (string) $this->input($dateField);

        if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
            && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
            $errors->add('level_arm_id', __('That class is not part of the selected level.'));
        }

        if ($periodId && $sessionId && ! $errors->has('academic_period_id') && ! $errors->has('academic_session_id')
            && ! AcademicPeriod::query()->whereKey($periodId)->where('academic_session_id', $sessionId)->exists()) {
            $errors->add('academic_period_id', __('That term is not part of the selected session.'));
        }

        if ($subjectId && $levelId && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')
            && ! $this->subjectOfferedByLevel((int) $subjectId, (int) $levelId)) {
            $errors->add('subject_id', __('That subject is not offered by the selected level.'));
        }

        if ($errors->isNotEmpty() || $date === '') {
            return;
        }

        $session = AcademicSession::query()->find($sessionId);
        if ($session && ($date < $session->starts_on->toDateString() || $date > $session->ends_on->toDateString())) {
            $errors->add($dateField, __('That date is outside the selected session.'));
        }

        if (! $errors->has('academic_period_id')) {
            $period = AcademicPeriod::query()->find($periodId);
            if ($period && ($date < $period->starts_on->toDateString() || $date > $period->ends_on->toDateString())) {
                $errors->add($dateField, __('That date is outside the selected term.'));
            }
        }
    }

    private function subjectOfferedByLevel(int $subjectId, int $levelId): bool
    {
        return DB::table('level_subject')
            ->where('academic_level_id', $levelId)
            ->where('subject_id', $subjectId)
            ->exists();
    }
}
