<?php

namespace Tests\Unit\Enums;

use App\Enums\AssessmentPurpose;
use App\Enums\ResultAdjustmentStatus;
use App\Enums\ResultRunStatus;
use App\Enums\ScoreSource;
use Tests\TestCase;

class ResultsEnumsTest extends TestCase
{
    public function test_result_run_status_values_and_behaviour(): void
    {
        $this->assertSame(
            ['draft', 'compiled', 'reviewed', 'approved', 'published', 'locked'],
            array_map(fn ($c) => $c->value, ResultRunStatus::all()),
        );

        $this->assertTrue(ResultRunStatus::Draft->recompilable());
        $this->assertTrue(ResultRunStatus::Compiled->recompilable());
        $this->assertTrue(ResultRunStatus::Reviewed->recompilable());
        $this->assertFalse(ResultRunStatus::Approved->recompilable());
        $this->assertFalse(ResultRunStatus::Published->recompilable());
        $this->assertFalse(ResultRunStatus::Locked->recompilable());

        $this->assertFalse(ResultRunStatus::Reviewed->requiresAdjustment());
        $this->assertTrue(ResultRunStatus::Approved->requiresAdjustment());
        $this->assertTrue(ResultRunStatus::Published->requiresAdjustment());
        $this->assertTrue(ResultRunStatus::Locked->requiresAdjustment());
        $this->assertTrue(ResultRunStatus::Locked->isLocked());
    }

    public function test_result_adjustment_status_values_and_behaviour(): void
    {
        $this->assertSame(['pending', 'applied', 'rejected'], array_map(fn ($c) => $c->value, ResultAdjustmentStatus::all()));
        $this->assertFalse(ResultAdjustmentStatus::Pending->isDecided());
        $this->assertTrue(ResultAdjustmentStatus::Applied->isDecided());
        $this->assertTrue(ResultAdjustmentStatus::Rejected->isDecided());
    }

    public function test_assessment_purpose_values_and_behaviour(): void
    {
        $this->assertSame(['academic', 'practice', 'entry_placement'], array_map(fn ($c) => $c->value, AssessmentPurpose::all()));
        $this->assertTrue(AssessmentPurpose::Academic->countsTowardResults());
        $this->assertFalse(AssessmentPurpose::Practice->countsTowardResults());
        $this->assertFalse(AssessmentPurpose::EntryPlacement->countsTowardResults());
    }

    public function test_score_source_values(): void
    {
        $this->assertSame(['manual', 'online_cbt', 'imported'], array_map(fn ($c) => $c->value, ScoreSource::all()));
    }
}
