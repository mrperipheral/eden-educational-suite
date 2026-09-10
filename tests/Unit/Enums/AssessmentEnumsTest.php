<?php

namespace Tests\Unit\Enums;

use App\Enums\AssessmentStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AssignmentSubmissionStatus;
use Tests\TestCase;

class AssessmentEnumsTest extends TestCase
{
    public function test_assessment_status_values_and_behaviour(): void
    {
        $this->assertSame(['draft', 'published', 'locked'], array_map(fn ($c) => $c->value, AssessmentStatus::all()));

        $this->assertTrue(AssessmentStatus::Draft->structureEditable());
        $this->assertFalse(AssessmentStatus::Published->structureEditable());
        $this->assertFalse(AssessmentStatus::Locked->structureEditable());

        $this->assertTrue(AssessmentStatus::Draft->acceptsScores());
        $this->assertTrue(AssessmentStatus::Published->acceptsScores());
        $this->assertFalse(AssessmentStatus::Locked->acceptsScores());

        $this->assertTrue(AssessmentStatus::Locked->isLocked());

        foreach (AssessmentStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }
    }

    public function test_assignment_status_values_and_behaviour(): void
    {
        $this->assertSame(['draft', 'published', 'closed'], array_map(fn ($c) => $c->value, AssignmentStatus::all()));

        $this->assertTrue(AssignmentStatus::Draft->structureEditable());
        $this->assertFalse(AssignmentStatus::Published->structureEditable());

        $this->assertTrue(AssignmentStatus::Published->tracksCompletion());
        $this->assertFalse(AssignmentStatus::Closed->tracksCompletion());
        $this->assertTrue(AssignmentStatus::Closed->isClosed());
    }

    public function test_assignment_submission_status_values_and_behaviour(): void
    {
        $this->assertSame(
            ['pending', 'submitted', 'late', 'exempt'],
            array_map(fn ($c) => $c->value, AssignmentSubmissionStatus::all()),
        );

        $this->assertFalse(AssignmentSubmissionStatus::Pending->isTurnedIn());
        $this->assertTrue(AssignmentSubmissionStatus::Submitted->isTurnedIn());
        $this->assertTrue(AssignmentSubmissionStatus::Late->isTurnedIn());
        $this->assertFalse(AssignmentSubmissionStatus::Exempt->isTurnedIn());
    }
}
