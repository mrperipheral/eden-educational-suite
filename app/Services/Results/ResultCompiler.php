<?php

namespace App\Services\Results;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\GradingScheme;
use App\Models\ResultAdjustment;
use App\Models\ResultRun;
use App\Models\Student;
use App\Models\StudentSubjectResult;
use App\Models\StudentSubjectResultComponent;
use App\Models\Subject;
use App\Models\User;
use App\Support\Results\AttendanceSummarizer;
use App\Support\Results\RankingCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compiles a {@see ResultRun} from **locked, academic-purpose** M14
 * {@see Assessment} scores. See `docs/results-report-cards.md` §"Result
 * compilation" / §"Missing scores".
 *
 * Never manufactures a missing score as zero: {@see self::compile()} first
 * computes everything in memory and collects every gap as a
 * {@see CompilationIssue}; if any exist, nothing is written and
 * {@see CompilationOutcome::$success} is `false`. Only once every eligible
 * student has a real score in every weighted category of every subject that
 * was actually assessed does it persist — one bulk delete + two bulk inserts
 * for the subject-result tier, one bulk insert for the overall tier, never a
 * query per student.
 *
 * "Subjects in scope" is derived from the data, not the academic catalogue: a
 * subject enters a run only if it has at least one **locked** assessment in
 * this run's (session, period, level, arm) — a subject nobody has assessed
 * yet this term simply does not appear, it is not an error.
 */
class ResultCompiler
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function compile(ResultRun $run, User $by): CompilationOutcome
    {
        $run->loadMissing([
            'period',
            'weightingScheme',
            'gradingScheme.grades' => fn ($q) => $q->active()->ordered(),
        ]);

        $students = $run->eligibleStudents()->get();

        if ($students->isEmpty()) {
            return CompilationOutcome::blocked([CompilationIssue::noStudents()]);
        }

        $subjectIds = Assessment::query()
            ->where('academic_session_id', $run->academic_session_id)
            ->where('academic_period_id', $run->academic_period_id)
            ->where('academic_level_id', $run->academic_level_id)
            ->where('level_arm_id', $run->level_arm_id)
            ->where('status', 'locked')
            ->where('purpose', 'academic')
            ->distinct()
            ->pluck('subject_id');

        if ($subjectIds->isEmpty()) {
            return CompilationOutcome::blocked([CompilationIssue::noSubjects()]);
        }

        $subjects = Subject::query()->whereKey($subjectIds)->get()->keyBy('id');

        $weightingItems = $run->weightingScheme->items()->with('category')->get();

        $lockedAssessments = Assessment::query()
            ->where('academic_session_id', $run->academic_session_id)
            ->where('academic_period_id', $run->academic_period_id)
            ->where('academic_level_id', $run->academic_level_id)
            ->where('level_arm_id', $run->level_arm_id)
            ->where('status', 'locked')
            ->where('purpose', 'academic')
            ->whereIn('subject_id', $subjectIds)
            ->get();

        $scoresByAssessment = AssessmentScore::query()
            ->whereIn('assessment_id', $lockedAssessments->pluck('id'))
            ->get()
            ->groupBy('assessment_id');

        $issues = [];
        // subjectId => studentId => ['components' => [...], 'percentage' => float]
        $computed = [];

        foreach ($subjectIds as $subjectId) {
            $subjectName = $subjects[$subjectId]->name;
            $subjectAssessments = $lockedAssessments->where('subject_id', $subjectId);

            $categoryResults = [];   // categoryId => ['name','weight','byStudent' => [studentId => ['raw','max']]]
            $subjectBlocked = false;

            foreach ($weightingItems as $item) {
                $categoryAssessments = $subjectAssessments->where('assessment_category_id', $item->assessment_category_id);

                if ($categoryAssessments->isEmpty()) {
                    $issues[] = CompilationIssue::categoryNotAssessed($subjectName, $item->category->name);
                    $subjectBlocked = true;

                    continue;
                }

                $byStudent = [];
                foreach ($students as $student) {
                    $rows = $categoryAssessments->flatMap(
                        fn (Assessment $a) => $scoresByAssessment->get($a->id, collect())->where('student_id', $student->id),
                    );

                    if ($rows->isEmpty()) {
                        $issues[] = CompilationIssue::studentNotAssessed($subjectName, $item->category->name, $student->shortName());
                        $subjectBlocked = true;

                        continue;
                    }

                    $unentered = $rows->first(fn (AssessmentScore $s) => $s->score === null);
                    if ($unentered !== null) {
                        $title = $categoryAssessments->firstWhere('id', $unentered->assessment_id)?->title ?? $item->category->name;
                        $issues[] = CompilationIssue::scoreMissing($subjectName, $student->shortName(), $title);
                        $subjectBlocked = true;

                        continue;
                    }

                    $percentages = $rows->map(function (AssessmentScore $s) use ($categoryAssessments) {
                        $max = (float) $categoryAssessments->firstWhere('id', $s->assessment_id)?->max_score;

                        return $max > 0 ? (float) $s->score / $max * 100 : 0.0;
                    });

                    $byStudent[$student->id] = [
                        'percentage' => round((float) $percentages->avg(), 2),
                        // Summed across every locked assessment in this category (usually
                        // one) — the raw points behind the percentage, for the report card.
                        'raw' => round((float) $rows->sum(fn (AssessmentScore $s) => (float) $s->score), 2),
                        'max' => round((float) $rows->sum(fn (AssessmentScore $s) => (float) $categoryAssessments->firstWhere('id', $s->assessment_id)?->max_score), 2),
                    ];
                }

                $categoryResults[$item->assessment_category_id] = [
                    'name' => $item->category->name,
                    'weight' => (float) $item->weight_percentage,
                    'byStudent' => $byStudent,
                ];
            }

            if ($subjectBlocked) {
                continue;
            }

            foreach ($students as $student) {
                $components = [];
                $percentage = 0.0;

                foreach ($categoryResults as $categoryId => $data) {
                    $entry = $data['byStudent'][$student->id];
                    $scorePct = $entry['percentage'];
                    $contribution = round($scorePct * $data['weight'] / 100, 2);
                    $percentage += $contribution;

                    $components[] = [
                        'assessment_category_id' => $categoryId,
                        'category_name_snapshot' => $data['name'],
                        'weight_percentage_snapshot' => $data['weight'],
                        'raw_score' => $entry['raw'],
                        'raw_max_score' => $entry['max'],
                        'score_percentage' => $scorePct,
                        'weighted_contribution' => $contribution,
                    ];
                }

                $computed[$subjectId][$student->id] = [
                    'percentage' => round($percentage, 2),
                    'components' => $components,
                ];
            }
        }

        if ($issues !== []) {
            return CompilationOutcome::blocked($issues);
        }

        $this->persist($run, $by, $students, $computed);

        return CompilationOutcome::ok(count($computed), $students->count());
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  array<int, array<int, array{percentage: float, components: list<array<string, mixed>>}>>  $computed
     */
    private function persist(ResultRun $run, User $by, Collection $students, array $computed): void
    {
        $schoolId = $this->tenant->idOrFail();
        $now = now();

        DB::transaction(function () use ($run, $by, $students, $computed, $schoolId, $now) {
            $existingIds = $run->subjectResults()->pluck('id');
            StudentSubjectResultComponent::query()->whereIn('student_subject_result_id', $existingIds)->delete();
            $run->subjectResults()->delete();
            $run->studentResults()->delete();

            $gradingScheme = $run->gradingScheme;
            $attendance = AttendanceSummarizer::summarize(
                $students->pluck('id')->all(), $run->academic_session_id, $run->academic_period_id,
            );

            $subjectRows = [];
            foreach ($computed as $subjectId => $byStudent) {
                foreach ($byStudent as $studentId => $data) {
                    $subjectRows[] = [
                        'school_id' => $schoolId,
                        'result_run_id' => $run->id,
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'academic_session_id' => $run->academic_session_id,
                        'academic_period_id' => $run->academic_period_id,
                        'academic_level_id' => $run->academic_level_id,
                        'level_arm_id' => $run->level_arm_id,
                        'percentage' => $data['percentage'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($subjectRows, 500) as $chunk) {
                DB::table('student_subject_results')->insert($this->withGrade($chunk, $gradingScheme));
            }

            $insertedIds = StudentSubjectResult::query()->where('result_run_id', $run->id)
                ->get(['id', 'student_id', 'subject_id'])
                ->groupBy('student_id')
                ->map(fn ($rows) => $rows->pluck('id', 'subject_id'));

            $componentRows = [];
            foreach ($computed as $subjectId => $byStudent) {
                foreach ($byStudent as $studentId => $data) {
                    $subjectResultId = $insertedIds[$studentId][$subjectId];
                    foreach ($data['components'] as $position => $component) {
                        $componentRows[] = [
                            'school_id' => $schoolId,
                            'student_subject_result_id' => $subjectResultId,
                            ...$component,
                            'position' => $position,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($componentRows, 500) as $chunk) {
                DB::table('student_subject_result_components')->insert($chunk);
            }

            $this->persistStudentResults($run, $students, $computed, $attendance, $schoolId, $now);

            $run->markCompiled($by);
        });

        $this->recomputeRanking($run->fresh());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withGrade(array $rows, GradingScheme $scheme): array
    {
        return array_map(function (array $row) use ($scheme) {
            $grade = $scheme->gradeFor((float) $row['percentage']);
            $row['grading_scheme_grade_id'] = $grade?->id;
            $row['grade_code_snapshot'] = $grade?->code;
            $row['grade_remark_snapshot'] = $grade?->remark;

            return $row;
        }, $rows);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  array<int, array<int, array{percentage: float, components: list<array<string, mixed>>}>>  $computed
     * @param  array<int, array{opened: int, present: int, absent: int, percentage: float|null}>  $attendance
     */
    private function persistStudentResults(ResultRun $run, Collection $students, array $computed, array $attendance, int $schoolId, Carbon $now): void
    {
        $gradingScheme = $run->gradingScheme;
        $rows = [];

        foreach ($students as $student) {
            $percentages = [];
            foreach ($computed as $bySubject) {
                if (isset($bySubject[$student->id])) {
                    $percentages[] = $bySubject[$student->id]['percentage'];
                }
            }

            $total = round(array_sum($percentages), 2);
            $count = count($percentages);
            $average = $count > 0 ? round($total / $count, 2) : 0.0;
            $grade = $gradingScheme->gradeFor($average);
            $att = $attendance[$student->id] ?? ['opened' => 0, 'present' => 0, 'absent' => 0, 'percentage' => null];

            $rows[] = [
                'school_id' => $schoolId,
                'result_run_id' => $run->id,
                'student_id' => $student->id,
                'academic_session_id' => $run->academic_session_id,
                'academic_period_id' => $run->academic_period_id,
                'academic_level_id' => $run->academic_level_id,
                'level_arm_id' => $run->level_arm_id,
                'total_percentage' => $total,
                'average_percentage' => $average,
                'subject_count' => $count,
                'overall_grade_code_snapshot' => $grade?->code,
                'overall_grade_remark_snapshot' => $grade?->remark,
                'position' => null,
                'class_size' => $students->count(),
                'days_school_opened' => $att['opened'] > 0 ? $att['opened'] : null,
                'days_present' => $att['opened'] > 0 ? $att['present'] : null,
                'days_absent' => $att['opened'] > 0 ? $att['absent'] : null,
                'attendance_percentage' => $att['percentage'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('student_results')->insert($chunk);
        }
    }

    /**
     * Recompute every student's overall total/average/grade from their
     * (possibly just-adjusted) subject percentages, then re-rank — used after
     * compile and after a {@see ResultAdjustment} is applied (one
     * changed subject score can move that student's overall average, and
     * therefore everyone's position).
     */
    public function recomputeRanking(ResultRun $run): void
    {
        $run->loadMissing(['gradingScheme.grades' => fn ($q) => $q->active()->ordered()]);

        DB::transaction(function () use ($run) {
            $this->refreshOverallTotals($run);

            if (! $run->ranking_enabled) {
                $run->studentResults()->update(['position' => null]);
                $run->subjectResults()->update(['subject_position' => null]);

                return;
            }

            $overall = $run->studentResults()->pluck('average_percentage', 'id')
                ->map(fn ($v) => round((float) $v, 2));
            $positions = RankingCalculator::rank($overall->all());
            $this->bulkUpdateById('student_results', array_map(fn ($rank) => ['position' => $rank], $positions));

            $bySubject = $run->subjectResults()->get(['id', 'subject_id', 'percentage'])->groupBy('subject_id');
            $subjectPositions = [];
            foreach ($bySubject as $rows) {
                $scores = $rows->pluck('percentage', 'id')->map(fn ($v) => round((float) $v, 2));
                foreach (RankingCalculator::rank($scores->all()) as $id => $rank) {
                    $subjectPositions[$id] = ['subject_position' => $rank];
                }
            }
            $this->bulkUpdateById('student_subject_results', $subjectPositions);
        });
    }

    /**
     * Recompute `total_percentage` / `average_percentage` / `overall_grade_*`
     * for every {@see StudentResult} in the run from the (current)
     * {@see StudentSubjectResult} percentages — one query to read every
     * subject percentage, one bulk update, never a query per student.
     */
    private function refreshOverallTotals(ResultRun $run): void
    {
        $totals = $run->subjectResults()->get(['student_id', 'percentage'])
            ->groupBy('student_id')
            ->map(fn ($rows) => [
                'total' => round((float) $rows->sum(fn ($r) => (float) $r->percentage), 2),
                'count' => $rows->count(),
            ]);

        $gradingScheme = $run->gradingScheme;
        $updates = [];

        foreach ($run->studentResults()->get(['id', 'student_id']) as $studentResult) {
            $data = $totals->get($studentResult->student_id);

            if ($data === null) {
                continue;
            }

            $average = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0.0;
            $grade = $gradingScheme->gradeFor($average);

            $updates[$studentResult->id] = [
                'total_percentage' => $data['total'],
                'average_percentage' => $average,
                'subject_count' => $data['count'],
                'overall_grade_code_snapshot' => $grade?->code,
                'overall_grade_remark_snapshot' => $grade?->remark,
            ];
        }

        $this->bulkUpdateById('student_results', $updates);
    }

    /**
     * Update many rows of `$table` with different values per row in one
     * statement per 500-row chunk (`UPDATE ... SET col = CASE id WHEN ...
     * END WHERE id IN (...)`) instead of one query per row — every row in
     * `$rows` must share the same set of columns.
     *
     * @param  array<int, array<string, mixed>>  $rows  id => [column => value]
     */
    private function bulkUpdateById(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys(reset($rows));

        foreach (array_chunk($rows, 500, true) as $chunk) {
            $ids = array_keys($chunk);
            $sets = [];
            $bindings = [];

            foreach ($columns as $column) {
                $case = "`{$column}` = CASE `id` ";
                foreach ($chunk as $id => $data) {
                    $case .= 'WHEN ? THEN ? ';
                    $bindings[] = $id;
                    $bindings[] = $data[$column];
                }
                $sets[] = $case.'END';
            }

            $bindings = [...$bindings, ...$ids];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            DB::update(
                "UPDATE `{$table}` SET ".implode(', ', $sets)." WHERE `id` IN ({$placeholders})",
                $bindings,
            );
        }
    }
}
