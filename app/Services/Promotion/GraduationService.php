<?php

namespace App\Services\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a student to `StudentStatus::Graduated` — never a deletion,
 * never a rewrite of history. The student's current `Enrollment` (if any)
 * is closed (`completed`) exactly as an ordinary class change already does;
 * every other historical enrollment/result is untouched. See
 * `docs/promotion.md`.
 *
 * `App\Models\Student::graduated_at` / `graduated_academic_session_id` /
 * `graduation_notes` / `graduated_by` are the entire audit trail — a
 * student is graduated at most once at a time, so there is no separate
 * history table to keep in sync (M21 spec §8: "do not introduce competing
 * lifecycle fields"). {@see self::reactivate()} is the explicit, authorised
 * reversal the spec requires before normal enrollment becomes possible
 * again (see `App\Http\Requests\Student\EnrollmentRequest`).
 */
class GraduationService
{
    /** @throws PromotionException */
    public function graduate(Student $student, AcademicSession $session, ?string $notes, User $by): void
    {
        DB::transaction(function () use ($student, $session, $notes, $by) {
            $locked = Student::query()->whereKey($student->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new PromotionException(__('Student not found.'));
            }

            if ($locked->status === StudentStatus::Graduated) {
                throw new PromotionException(__('Student is already graduated.'));
            }

            if ($locked->status !== StudentStatus::Active) {
                throw new PromotionException(__('Only an active student can be graduated.'));
            }

            $current = $locked->currentEnrollment;

            if ($current !== null) {
                $current->status = EnrollmentStatus::Completed;
                $current->ended_on ??= Carbon::now()->toDateString();
                $current->save();
            }

            $locked->status = StudentStatus::Graduated;
            $locked->graduated_at = Carbon::now();
            $locked->graduated_academic_session_id = $session->getKey();
            $locked->graduation_notes = $notes;
            $locked->graduated_by = $by->getKey();
            $locked->save();
        });
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array{graduated: int, failed: int, failures: array<int, string>}
     */
    public function graduateBatch(Collection $students, AcademicSession $session, ?string $notes, User $by): array
    {
        $graduated = 0;
        $failures = [];

        foreach ($students as $student) {
            try {
                $this->graduate($student, $session, $notes, $by);
                $graduated++;
            } catch (PromotionException $e) {
                $failures[$student->getKey()] = $e->getMessage();
            }
        }

        return ['graduated' => $graduated, 'failed' => count($failures), 'failures' => $failures];
    }

    /** Reverse a graduation — the explicit, authorised reversal M21 requires. */
    public function reactivate(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $locked = Student::query()->whereKey($student->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== StudentStatus::Graduated) {
                throw new PromotionException(__('Student is not currently graduated.'));
            }

            $locked->status = StudentStatus::Active;
            $locked->graduated_at = null;
            $locked->graduated_academic_session_id = null;
            $locked->graduation_notes = null;
            $locked->graduated_by = null;
            $locked->save();
        });
    }
}
