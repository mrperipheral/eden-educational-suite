<?php

namespace App\Services\Results;

/**
 * The result of one {@see ResultCompiler::compile()} attempt.
 */
final readonly class CompilationOutcome
{
    /**
     * @param  list<CompilationIssue>  $issues
     */
    private function __construct(
        public bool $success,
        public array $issues,
        public int $subjectCount = 0,
        public int $studentCount = 0,
    ) {}

    public static function blocked(array $issues): self
    {
        return new self(false, $issues);
    }

    public static function ok(int $subjectCount, int $studentCount): self
    {
        return new self(true, [], $subjectCount, $studentCount);
    }
}
