<?php

namespace App\Services\Results;

/**
 * One reason a {@see ResultCompiler} refused to compile a result run — see
 * `docs/results-report-cards.md` §"Missing scores". Never manufactured as a
 * zero; always surfaced to the user so they know exactly what to fix.
 */
final readonly class CompilationIssue
{
    public function __construct(
        public string $type,
        public string $subjectName,
        public ?string $categoryName = null,
        public ?string $studentName = null,
        public ?string $assessmentTitle = null,
    ) {}

    public static function categoryNotAssessed(string $subjectName, string $categoryName): self
    {
        return new self('category_not_assessed', $subjectName, categoryName: $categoryName);
    }

    public static function studentNotAssessed(string $subjectName, string $categoryName, string $studentName): self
    {
        return new self('student_not_assessed', $subjectName, $categoryName, $studentName);
    }

    public static function scoreMissing(string $subjectName, string $studentName, string $assessmentTitle): self
    {
        return new self('score_missing', $subjectName, studentName: $studentName, assessmentTitle: $assessmentTitle);
    }

    public static function noStudents(): self
    {
        return new self('no_students', __('this class'));
    }

    public static function noSubjects(): self
    {
        return new self('no_subjects', __('this class'));
    }

    public function message(): string
    {
        return match ($this->type) {
            'category_not_assessed' => __(':subject — no locked ":category" assessment has been recorded for this class.', [
                'subject' => $this->subjectName, 'category' => $this->categoryName,
            ]),
            'student_not_assessed' => __(':subject — :student has no ":category" score recorded.', [
                'subject' => $this->subjectName, 'category' => $this->categoryName, 'student' => $this->studentName,
            ]),
            'score_missing' => __(':subject — :student\'s score for ":assessment" has not been entered.', [
                'subject' => $this->subjectName, 'student' => $this->studentName, 'assessment' => $this->assessmentTitle,
            ]),
            'no_students' => __('No students are enrolled in this class for this term.'),
            'no_subjects' => __('No locked assessments have been recorded for this class this term.'),
            default => __('An assessment score is missing.'),
        };
    }
}
