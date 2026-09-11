<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ReportCardConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Which predefined fields a school's report card shows. School-owned
 * ({@see BelongsToSchool}). Every field is its own typed boolean column — see
 * the migration for the full list and `docs/results-report-cards.md` §"Report
 * card configuration".
 *
 * Scope: `academic_session_id` / `academic_period_id` are both optionally
 * null. `forScope()` resolves the most specific configured row for a given
 * session/period — falling back to an **unsaved** default instance (this
 * model's own attribute defaults, all fields on) when the school has
 * configured nothing yet, so a report card always renders something sensible.
 *
 * At most one row may exist per exact scope — enforced in
 * `App\Http\Requests\Results\ReportCardConfigurationRequest` via a find-or-update
 * upsert (a DB unique index backstops only the fully-specific, non-null case;
 * MySQL/SQLite do not enforce uniqueness across NULLable columns the way this
 * "one school-wide default" rule needs).
 *
 * `principal_signature_path` / `class_teacher_signature_path` follow the exact
 * private-disk pattern `App\Models\SchoolSetting::LOGO_DISK` uses for the
 * school logo — never web-served directly.
 */
class ReportCardConfiguration extends Model
{
    /** @use HasFactory<ReportCardConfigurationFactory> */
    use BelongsToSchool, HasFactory;

    /** The private disk that holds signature images (never web-served directly). */
    public const SIGNATURE_DISK = 'local';

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'name',
        'show_student_name',
        'show_admission_number',
        'show_student_photo',
        'show_class_level',
        'show_class_arm',
        'show_session',
        'show_term',
        'show_subject_components',
        'show_subject_percentage',
        'show_subject_grade',
        'show_subject_remark',
        'show_subject_position',
        'show_overall_total',
        'show_overall_average',
        'show_overall_position',
        'show_overall_class_size',
        'show_attendance_days_opened',
        'show_attendance_days_present',
        'show_attendance_days_absent',
        'show_attendance_percentage',
        'show_class_teacher_comment',
        'show_principal_comment',
        'show_class_teacher_signature',
        'show_principal_signature',
    ];

    /**
     * Defaults for a brand-new / unsaved row — every field visible. Kept in
     * sync with the migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'name' => 'Default',
        'show_student_name' => true,
        'show_admission_number' => true,
        'show_student_photo' => true,
        'show_class_level' => true,
        'show_class_arm' => true,
        'show_session' => true,
        'show_term' => true,
        'show_subject_components' => true,
        'show_subject_percentage' => true,
        'show_subject_grade' => true,
        'show_subject_remark' => true,
        'show_subject_position' => true,
        'show_overall_total' => true,
        'show_overall_average' => true,
        'show_overall_position' => true,
        'show_overall_class_size' => true,
        'show_attendance_days_opened' => true,
        'show_attendance_days_present' => true,
        'show_attendance_days_absent' => true,
        'show_attendance_percentage' => true,
        'show_class_teacher_comment' => true,
        'show_principal_comment' => true,
        'show_class_teacher_signature' => true,
        'show_principal_signature' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_student_name' => 'boolean',
            'show_admission_number' => 'boolean',
            'show_student_photo' => 'boolean',
            'show_class_level' => 'boolean',
            'show_class_arm' => 'boolean',
            'show_session' => 'boolean',
            'show_term' => 'boolean',
            'show_subject_components' => 'boolean',
            'show_subject_percentage' => 'boolean',
            'show_subject_grade' => 'boolean',
            'show_subject_remark' => 'boolean',
            'show_subject_position' => 'boolean',
            'show_overall_total' => 'boolean',
            'show_overall_average' => 'boolean',
            'show_overall_position' => 'boolean',
            'show_overall_class_size' => 'boolean',
            'show_attendance_days_opened' => 'boolean',
            'show_attendance_days_present' => 'boolean',
            'show_attendance_days_absent' => 'boolean',
            'show_attendance_percentage' => 'boolean',
            'show_class_teacher_comment' => 'boolean',
            'show_principal_comment' => 'boolean',
            'show_class_teacher_signature' => 'boolean',
            'show_principal_signature' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ResultRun, $this>
     */
    public function resultRun(): BelongsTo
    {
        return $this->belongsTo(ResultRun::class);
    }

    /**
     * The configuration to render a report card with for a given session /
     * period, with precedence: exact (session + period) > session-wide
     * (period null) > school-wide default (both null) > an unsaved,
     * all-fields-on instance if the school has configured nothing at all.
     * Never returns a per-run snapshot row — see {@see self::forRun()}.
     */
    public static function forScope(?int $academicSessionId, ?int $academicPeriodId): self
    {
        $candidates = array_filter([
            [$academicSessionId, $academicPeriodId],
            [$academicSessionId, null],
            [null, null],
        ], fn ($pair) => $pair[0] !== null || $pair === [null, null]);

        foreach (array_unique($candidates, SORT_REGULAR) as [$sessionId, $periodId]) {
            $match = static::query()
                ->whereNull('result_run_id')
                ->where('academic_session_id', $sessionId)
                ->where('academic_period_id', $periodId)
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        return new self;
    }

    /**
     * The row for one exact scope — never a run snapshot — creating an
     * unsaved one if it doesn't exist yet. Snapshot rows deliberately share
     * the same (null, null) scope as the school-wide default (see
     * {@see self::snapshotForRun()}), so every read/write of a *live* scope
     * config must exclude them explicitly via `whereNull('result_run_id')`
     * rather than matching on scope columns alone — otherwise a save here
     * could silently overwrite a locked run's historical snapshot.
     */
    public static function exactScopeRow(?int $academicSessionId, ?int $academicPeriodId): self
    {
        return static::query()
            ->whereNull('result_run_id')
            ->where('academic_session_id', $academicSessionId)
            ->where('academic_period_id', $academicPeriodId)
            ->first() ?? tap(new self, function (self $row) use ($academicSessionId, $academicPeriodId) {
                $row->academic_session_id = $academicSessionId;
                $row->academic_period_id = $academicPeriodId;
            });
    }

    /**
     * The frozen field-toggle snapshot for one published/locked run, if it has
     * one yet (a run gets one the first time it is published — see
     * `App\Http\Controllers\Results\ResultRunController::publish()`).
     */
    public static function forRun(ResultRun $run): ?self
    {
        return static::query()->where('result_run_id', $run->id)->first();
    }

    /**
     * Snapshot the live scope-resolved configuration's field toggles onto a
     * new row tied to this run. Idempotent — calling it again (e.g.
     * re-publishing after an unlock) replaces the existing snapshot rather
     * than accumulating rows. Signature *images* are deliberately **not**
     * copied (a locked report card's signature area still renders when its
     * toggle is on, but always shows whatever image is live at view time —
     * see `docs/results-report-cards.md`).
     */
    public static function snapshotForRun(ResultRun $run): self
    {
        $live = self::forScope($run->academic_session_id, $run->academic_period_id);

        // `getAttributes()` already carries every `show_*` default even on an
        // unsaved instance (Eloquent seeds `$attributes` from the class's
        // declared defaults on construction) — no separate fallback needed.
        $toggles = array_intersect_key($live->getAttributes(), array_flip(self::FIELDS));

        // Via the `reportCardSnapshot` relation so `result_run_id` is set from
        // the parent, exactly like every other child FK in this codebase —
        // it is deliberately not mass-assignable.
        return $run->reportCardSnapshot()->updateOrCreate(
            [],
            [...$toggles, 'name' => $live->name.' (locked snapshot)'],
        );
    }

    /** Every predefined, independently togglable report-card field. */
    public const FIELDS = [
        'show_student_name', 'show_admission_number', 'show_student_photo',
        'show_class_level', 'show_class_arm', 'show_session', 'show_term',
        'show_subject_components', 'show_subject_percentage', 'show_subject_grade',
        'show_subject_remark', 'show_subject_position',
        'show_overall_total', 'show_overall_average', 'show_overall_position', 'show_overall_class_size',
        'show_attendance_days_opened', 'show_attendance_days_present',
        'show_attendance_days_absent', 'show_attendance_percentage',
        'show_class_teacher_comment', 'show_principal_comment',
        'show_class_teacher_signature', 'show_principal_signature',
    ];

    public function hasPrincipalSignature(): bool
    {
        return $this->principal_signature_path !== null
            && Storage::disk(self::SIGNATURE_DISK)->exists($this->principal_signature_path);
    }

    public function hasClassTeacherSignature(): bool
    {
        return $this->class_teacher_signature_path !== null
            && Storage::disk(self::SIGNATURE_DISK)->exists($this->class_teacher_signature_path);
    }

    public function putPrincipalSignature(string $path): void
    {
        $this->replaceSignature('principal_signature_path', $path);
    }

    public function putClassTeacherSignature(string $path): void
    {
        $this->replaceSignature('class_teacher_signature_path', $path);
    }

    public function clearPrincipalSignature(): void
    {
        $this->removeSignature('principal_signature_path');
    }

    public function clearClassTeacherSignature(): void
    {
        $this->removeSignature('class_teacher_signature_path');
    }

    private function replaceSignature(string $column, string $path): void
    {
        $previous = $this->{$column};

        $this->{$column} = $path;
        $this->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::SIGNATURE_DISK)->delete($previous);
        }
    }

    private function removeSignature(string $column): void
    {
        $path = $this->{$column};

        $this->{$column} = null;
        $this->save();

        if ($path !== null) {
            Storage::disk(self::SIGNATURE_DISK)->delete($path);
        }
    }
}
